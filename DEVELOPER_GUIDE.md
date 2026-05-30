# QuickSlot Developer Guide

Audience: developers extending, integrating, auditing, or maintaining QuickSlot.

## Contents

- [Architecture Overview](#architecture-overview)
- [Database Schema](#database-schema)
- [REST API](#rest-api)
- [Hooks](#hooks)
- [Coding Standards](#coding-standards)
- [Extension Examples](#extension-examples)

## Architecture Overview

QuickSlot is a modular WordPress plugin centered around a bootstrap class that loads feature areas on `plugins_loaded`, `rest_api_init`, and standard admin/frontend hooks.

### High-level flow

1. `quickslot.php` defines plugin constants and requirements.
2. `QuickSlot::init()` loads design, email, calendar, REST, and admin or frontend code.
3. `QS_Migration_Manager` ensures the schema is installed and current.
4. Frontend requests consume public REST endpoints for services, dates, slots, and booking creation.
5. Admin screens manage services, availability, bookings, settings, and integrations.

### Core Modules

| Module | Primary classes | Responsibility |
| --- | --- | --- |
| Services | `QS_Services_Admin`, `QS_Services_Endpoint` | Service CRUD and public service listing |
| Availability | `QS_Availability_Admin`, `QS_Slot_Generator`, `QS_Availability_Checker`, `QS_Availability_Endpoint` | Weekly rules, exceptions, slot generation, availability checks |
| Bookings | `QS_Booking_Handler`, `QS_Bookings_Admin`, `QS_Bookings_Endpoint` | Booking creation, overlap protection, admin management |
| Email | `QS_Email_Templates`, `QS_Mailer`, `QS_Email_Logger`, `QS_Reminder_Scheduler` | Templates, sends, logging, reminders |
| Calendar | `QS_ICS_Generator`, `QS_Google_Calendar` | ICS generation, download URLs, Google OAuth and sync |
| API | `QS_REST_Controller` and endpoint classes | Public REST route registration |
| Design System | `QS_Color_Settings`, `QS_Design_System` | Color settings and CSS token injection |

### Booking lifecycle

1. Frontend loads services from `GET /quickslot/v1/services`
2. Frontend loads available dates from `GET /quickslot/v1/availability/dates`
3. Frontend loads slots from `GET /quickslot/v1/availability/slots`
4. Frontend submits booking to `POST /quickslot/v1/bookings`
5. `QS_Bookings_Endpoint` sanitizes, validates, rate-limits, and re-checks slot availability
6. `QS_Booking_Handler` writes the booking row, using transaction-aware overlap protection where possible
7. Hook-driven systems send emails, schedule reminders, and sync confirmed bookings to Google Calendar

## Database Schema

All tables use the WordPress table prefix. Table names below are shown without prefix.

### `qs_services`

Stores service definitions.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigint unsigned` | Primary key |
| `name` | `varchar(255)` | Service label |
| `description` | `text` | Optional |
| `duration` | `int unsigned` | Minutes |
| `price` | `decimal(10,2)` nullable | Optional price |
| `is_paid` | `tinyint(1)` | Paid/free flag |
| `status` | `varchar(20)` | Usually `active` or `inactive` |
| `created_at` | `datetime` | Insert timestamp |
| `updated_at` | `datetime` | Update timestamp |

Indexes:

- Primary key on `id`
- Key on `status`

### `qs_availability`

Stores weekly availability rules.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigint unsigned` | Primary key |
| `day_of_week` | `tinyint unsigned` | Sunday is `0`, Monday `1`, ... Saturday `6` |
| `is_available` | `tinyint(1)` | Day enabled/disabled |
| `start_time` | `time` nullable | Daily open time |
| `end_time` | `time` nullable | Daily close time |
| `breaks` | `longtext` nullable | JSON-encoded break windows |
| `buffer_minutes` | `int unsigned` | Gap between slots |
| `max_bookings` | `int unsigned` nullable | Optional daily booking cap |
| `updated_at` | `datetime` | Update timestamp |

Indexes:

- Primary key on `id`
- Key on `day_of_week`

### `qs_availability_exceptions`

Stores date-specific overrides.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigint unsigned` | Primary key |
| `exception_date` | `date` | Date override |
| `is_available` | `tinyint(1)` | `0` for blocked, `1` for custom hours |
| `start_time` | `time` nullable | Used for custom availability |
| `end_time` | `time` nullable | Used for custom availability |
| `reason` | `varchar(255)` nullable | Optional explanation |
| `created_at` | `datetime` | Insert timestamp |

Indexes:

- Primary key on `id`
- Key on `exception_date`

### `qs_bookings`

Stores booking records.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigint unsigned` | Primary key |
| `service_id` | `bigint unsigned` | Related service |
| `customer_name` | `varchar(255)` | Required |
| `customer_email` | `varchar(255)` | Required |
| `customer_phone` | `varchar(50)` nullable | Optional |
| `customer_note` | `text` nullable | Optional |
| `booking_start` | `datetime` | Stored in UTC |
| `booking_end` | `datetime` | Stored in UTC |
| `timezone` | `varchar(100)` | Customer/browser timezone or site fallback |
| `status` | `varchar(20)` | `pending`, `confirmed`, `cancelled`, `completed`, `no-show` |
| `payment_status` | `varchar(20)` | Currently defaults to `unpaid` |
| `booking_source` | `varchar(50)` | `frontend`, `admin`, or legacy `website` default |
| `calendar_provider` | `varchar(50)` nullable | Example: `google` |
| `calendar_event_id` | `varchar(191)` nullable | Synced event identifier |
| `reminder_sent` | `tinyint(1)` | Single reminder sent flag |
| `cancellation_token` | `varchar(64)` nullable | Used for ICS download tokenization |
| `created_at` | `datetime` | Insert timestamp |
| `updated_at` | `datetime` | Update timestamp |

Indexes:

- Primary key on `id`
- Keys on `service_id`, `booking_start`, `status`, `customer_email`, `cancellation_token`

### `qs_calendar_connections`

Stores provider connection state and encrypted OAuth tokens.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigint unsigned` | Primary key |
| `provider` | `varchar(50)` | Currently `google` |
| `calendar_id` | `varchar(191)` | Usually `primary` or a specific calendar ID |
| `access_token` | `longtext` nullable | Encrypted |
| `refresh_token` | `longtext` nullable | Encrypted |
| `token_expires_at` | `datetime` nullable | UTC-like expiry tracking |
| `is_connected` | `tinyint(1)` | Connection state |
| `connected_at` | `datetime` nullable | Original connection timestamp |
| `updated_at` | `datetime` | Update timestamp |

Indexes:

- Primary key on `id`
- Keys on `provider`, `is_connected`

### `qs_email_logs`

Stores email log entries.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigint unsigned` | Primary key |
| `booking_id` | `bigint unsigned` nullable | Related booking |
| `email_type` | `varchar(50)` | `confirmation`, `notification`, `reminder`, `cancellation` |
| `recipient_email` | `varchar(255)` | Recipient |
| `subject` | `varchar(255)` | Final subject line |
| `status` | `varchar(20)` | `pending`, `sent`, `failed` |
| `sent_at` | `datetime` nullable | Send timestamp |

Indexes:

- Primary key on `id`
- Keys on `booking_id`, `email_type`, `status`

## REST API

Base namespace:

```text
/wp-json/quickslot/v1
```

All documented endpoints are public in v1.0.1 and use `permission_callback => __return_true`.

### `GET /quickslot/v1/services`

Returns active services.

Response example:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Consultation",
      "description": "60-minute discovery session",
      "duration": 60,
      "price": 75,
      "is_paid": true
    }
  ]
}
```

Notes:

- Cached in the `qs_services_list` transient for 5 minutes
- Only returns services with `status = active`

### `GET /quickslot/v1/availability/dates`

Returns bookable dates for a service in a given month.

Query parameters:

| Parameter | Required | Format |
| --- | --- | --- |
| `service_id` | Yes | Integer |
| `month` | Yes | `YYYY-MM` |

Example request:

```text
GET /wp-json/quickslot/v1/availability/dates?service_id=1&month=2026-06
```

Response example:

```json
{
  "success": true,
  "data": [
    "2026-06-02",
    "2026-06-03",
    "2026-06-05"
  ]
}
```

Possible errors:

- `quickslot_invalid_service` with HTTP 400
- `quickslot_invalid_month` with HTTP 400
- `quickslot_service_not_found` with HTTP 404

### `GET /quickslot/v1/availability/slots`

Returns bookable slots for a service on a specific date.

Query parameters:

| Parameter | Required | Format |
| --- | --- | --- |
| `service_id` | Yes | Integer |
| `date` | Yes | `YYYY-MM-DD` |

Example request:

```text
GET /wp-json/quickslot/v1/availability/slots?service_id=1&date=2026-06-02
```

Response example:

```json
{
  "success": true,
  "data": [
    "09:00",
    "10:15",
    "11:30"
  ]
}
```

Possible errors:

- `quickslot_invalid_service` with HTTP 400
- `quickslot_invalid_date` with HTTP 400
- `quickslot_service_not_found` with HTTP 404

### `POST /quickslot/v1/bookings`

Creates a booking.

Request example:

```json
{
  "service_id": 1,
  "booking_date": "2026-06-02",
  "booking_time": "09:00",
  "customer_name": "Jane Doe",
  "customer_email": "jane@example.com",
  "customer_phone": "+1 555 0100",
  "customer_note": "Please call when you arrive.",
  "timezone": "America/New_York",
  "website": ""
}
```

Successful response example:

```json
{
  "success": true,
  "data": {
    "booking_id": 42,
    "ics_url": "https://example.com/?qs_ics_download=1&booking_id=42&token=..."
  }
}
```

Validation error example:

```json
{
  "success": false,
  "code": "validation_error",
  "message": "Please review the highlighted fields and try again.",
  "errors": {
    "customer_email": "Please enter a valid email address."
  }
}
```

Conflict error example:

```json
{
  "success": false,
  "code": "slot_unavailable",
  "message": "This time slot is no longer available."
}
```

Notes:

- HTTP 201 on success
- HTTP 422 on validation errors
- HTTP 409 when the slot is no longer available
- HTTP 429 on rate limiting
- The `website` field is a honeypot; non-empty values are treated as spam and ignored with a success response

## Hooks

QuickSlot exposes a small set of action and filter hooks suitable for extensions.

### Actions

#### `quickslot_booking_created`

Fires after a booking row is created.

Parameters:

- `int $booking_id`
- `array $booking_data`

Use cases:

- Push bookings to CRMs
- Trigger webhooks
- Create custom internal audit logs

#### `quickslot_booking_confirmed`

Fires when a booking is confirmed.

Parameters:

- `int $booking_id`

Use cases:

- Trigger downstream scheduling
- Sync with external systems

#### `quickslot_booking_cancelled`

Fires when a booking is cancelled.

Parameters:

- `int $booking_id`
- `string $reason`

Use cases:

- Clear downstream reservations
- Send system notifications

#### `quickslot_booking_status_changed`

Fires after an admin status change is saved.

Parameters:

- `int $booking_id`
- `string $old_status`
- `string $new_status`

#### `quickslot_reminder_sent`

Fires after a reminder email sends successfully.

Parameters:

- `int $booking_id`

#### `quickslot_before_send_email`

Fires immediately before `wp_mail()`.

Parameters:

- `string $type`
- `string $recipient`
- `int $booking_id`
- `array $booking`

#### `quickslot_after_send_email`

Fires immediately after `wp_mail()`.

Parameters:

- `string $type`
- `string $recipient`
- `int $booking_id`
- `bool $sent`

### Filters

#### `quickslot_available_slots`

Filters generated slots before they are returned.

Parameters:

- `array $slots`
- `int $service_id`
- `string $date_ymd`

#### `quickslot_email_template`

Filters a loaded email template.

Parameters:

- `array $template`
- `string $type`

#### `quickslot_ics_content`

Filters generated ICS content.

Parameters:

- `string $content`
- `int $booking_id`
- `array $booking`

### Additional discovered filter

QuickSlot also exposes:

- `quickslot_available_dates`

Parameters:

- `array $dates`
- `int $service_id`
- `string $month`

## Coding Standards

QuickSlot follows typical WordPress plugin conventions with some stricter modern PHP choices.

Observed standards in v1.0.1:

- `declare(strict_types=1);` used across core files
- Singleton pattern used for several infrastructure classes
- Direct access protection with `ABSPATH` checks
- Sanitization centralized through `QS_Sanitizer` and WordPress helpers
- DB writes use `$wpdb` with prepared statements where practical
- WordPress hooks drive side effects like mail, reminders, and sync

Extension guidance:

- Keep new logic hook-driven when possible
- Preserve UTC storage for booking timestamps
- Respect site timezone for availability and display behavior
- Avoid bypassing `QS_Booking_Handler` if you need overlap protection and downstream hooks
- Do not depend on undocumented transients as a stable public API

## Extension Examples

### Modify available slots

```php
add_filter('quickslot_available_slots', function (array $slots, int $service_id, string $date): array {
	if (1 !== $service_id) {
		return $slots;
	}

	return array_values(array_filter($slots, static function (string $slot): bool {
		return $slot !== '12:00';
	}));
}, 10, 3);
```

### Customize reminder subject line

```php
add_filter('quickslot_email_template', function (array $template, string $type): array {
	if ('reminder' !== $type) {
		return $template;
	}

	$template['subject'] = '[Reminder] ' . $template['subject'];
	return $template;
}, 10, 2);
```

### Append custom ICS content

```php
add_filter('quickslot_ics_content', function (string $content, int $booking_id, array $booking): string {
	return str_replace(
		"END:VEVENT",
		"X-CUSTOM-FIELD:InternalRef-{$booking_id}\r\nEND:VEVENT",
		$content
	);
}, 10, 3);
```

### Listen for confirmed bookings

```php
add_action('quickslot_booking_confirmed', function (int $booking_id): void {
	error_log('QuickSlot booking confirmed: ' . $booking_id);
});
```
