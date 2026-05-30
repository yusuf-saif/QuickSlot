# QuickSlot Administrator Guide

Audience: WordPress administrators, technical site owners, and support teams responsible for operating QuickSlot in production.

## Contents

- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Database Tables](#database-tables)
- [Cron and Action Scheduler Requirements](#cron-and-action-scheduler-requirements)
- [Email Requirements](#email-requirements)
- [Google Calendar OAuth Setup](#google-calendar-oauth-setup)
- [Security Considerations](#security-considerations)
- [Backup Recommendations](#backup-recommendations)
- [Upgrade Procedure](#upgrade-procedure)
- [Migration Framework](#migration-framework)
- [Troubleshooting](#troubleshooting)

## System Requirements

| Component | Requirement | Notes |
| --- | --- | --- |
| WordPress | 6.0+ | Enforced at activation |
| PHP | 8.0+ | Enforced at activation |
| Database | WordPress-compatible MySQL/MariaDB | InnoDB recommended |
| HTTPS | Recommended | Strongly recommended for admin and OAuth flows |
| OpenSSL | Recommended | Required for Google token encryption/decryption |
| Composer vendor packages | Required for Google sync | Specifically `google/apiclient` |

## Installation

1. Deploy the plugin into `/wp-content/plugins/quickslot/`.
2. If Google Calendar support is required, install plugin dependencies so `vendor/autoload.php` is available.
3. Activate the plugin from WordPress admin.
4. On activation, QuickSlot:
   - Verifies WordPress and PHP versions
   - Creates or updates required tables with `dbDelta`
   - Runs ordered migrations through the migration manager
5. Open `QuickSlot > Settings` and complete the base configuration.

## Database Tables

QuickSlot creates six custom tables using the current WordPress table prefix.

Examples below omit the prefix for readability.

| Table | Purpose |
| --- | --- |
| `qs_services` | Service definitions |
| `qs_availability` | Weekly availability rules |
| `qs_availability_exceptions` | Date-specific availability overrides |
| `qs_bookings` | Booking records |
| `qs_calendar_connections` | Google Calendar connection and token state |
| `qs_email_logs` | Email send log |

## Cron and Action Scheduler Requirements

QuickSlot reminder delivery depends on scheduled tasks.

Scheduling behavior:

- If Action Scheduler functions are available, QuickSlot schedules reminder actions with Action Scheduler.
- If Action Scheduler is not available, QuickSlot falls back to `wp_schedule_single_event()`.

Admin requirements:

- WP-Cron must run reliably, or reminders may be delayed or skipped.
- High-traffic or business-critical sites should use a real server cron trigger for `wp-cron.php`.
- If your environment already includes Action Scheduler, it is the preferred execution path.

## Email Requirements

QuickSlot sends:

- Customer booking emails
- Business notification emails
- Reminder emails
- Cancellation emails

Operational requirements:

- Configure a valid `Business Email` in `Settings > General`
- Use SMTP or a transactional email provider for production
- Monitor deliverability separately from plugin functionality

Email logging:

- QuickSlot logs send attempts to `qs_email_logs`
- Log statuses are stored as `pending`, `sent`, or `failed`

## Google Calendar OAuth Setup

QuickSlot uses Google OAuth for calendar connectivity.

Prerequisites:

- `google/apiclient` installed
- Google Calendar API enabled
- OAuth web application credentials
- Authorized redirect URI set to:

```text
https://example.com/wp-admin/admin-post.php?action=quickslot_google_oauth_callback
```

Replace `example.com` with your actual site domain.

Setup summary:

1. Save `qs_google_client_id`
2. Save `qs_google_client_secret`
3. Save `qs_google_calendar_id` or use `primary`
4. Start OAuth from `QuickSlot > Settings > Calendar`
5. Complete consent flow
6. Test the connection from the admin UI

Connection storage:

- Provider connection rows are stored in `qs_calendar_connections`
- Tokens are encrypted before storage
- Expired access tokens are refreshed automatically when a refresh token is available

## Security Considerations

QuickSlot includes several security controls administrators should understand.

### Access control

- Admin screens require `manage_options`
- Admin actions use WordPress nonces

### Input handling

- Booking form inputs are sanitized server-side
- REST requests are validated before booking creation
- A honeypot field helps ignore simple bot submissions

### Rate limiting

- Public booking creation uses a coarse transient-based rate limit by client IP
- Threshold: 10 attempts within 15 minutes for sufficiently complete booking attempts

### Calendar invite downloads

- ICS download URLs require a booking ID and a token
- The token is compared with `hash_equals()` against the stored cancellation token

### Google OAuth token security

- Tokens are encrypted with OpenSSL AES-256-CBC
- Encryption material is derived from WordPress salts and keys
- If OpenSSL is unavailable, token encryption/decryption cannot function correctly

## Backup Recommendations

Before updates or major settings changes:

1. Back up the WordPress database.
2. Back up the `quickslot` plugin directory.
3. Export current bookings to CSV.
4. If Google Calendar is connected, document the active Google project and calendar settings.

Priority tables to back up:

- `qs_services`
- `qs_availability`
- `qs_availability_exceptions`
- `qs_bookings`
- `qs_calendar_connections`
- `qs_email_logs`

## Upgrade Procedure

Recommended process:

1. Create a full database backup.
2. Export current bookings to CSV.
3. Deploy the updated plugin files.
4. If Google support is used, deploy updated Composer dependencies as needed.
5. Trigger the plugin in WordPress so `plugins_loaded` runs.
6. Confirm the plugin updates `quickslot_version` and `quickslot_db_version`.
7. Test:
   - Service listing
   - Availability dates and slots
   - Frontend booking creation
   - Booking status changes
   - Reminder scheduling
   - Google Calendar connection and sync

## Migration Framework

QuickSlot v1.0.1 uses an ordered migration framework in `QS_Migration_Manager`.

### Version options

| Option | Purpose |
| --- | --- |
| `quickslot_version` | Stores the installed plugin version |
| `quickslot_db_version` | Stores the installed database schema/migration version |

### Migration behavior

On `plugins_loaded`, QuickSlot checks both stored options against:

- `QUICKSLOT_VERSION`
- `QUICKSLOT_DB_VERSION`

If either installed version is missing or lower than the current version, QuickSlot runs `QS_Migration_Manager::run()`.

The migration sequence is:

1. Run base schema installation through `QS_Installer::install()`
2. Read `quickslot_db_version`
3. Run any ordered migrations newer than the installed DB version
4. Update `quickslot_db_version` after each successful migration step
5. Ensure `quickslot_db_version` is finally at `1.0.1`
6. Update `quickslot_version` to `1.0.1`

### Current DB version

- Current plugin version: `1.0.1`
- Current DB version: `1.0.1`

### v1.0.1 migration scope

The `1.0.1` migration aligns early schema differences by:

- Ensuring the canonical `customer_note` column exists on `qs_bookings`
- Renaming legacy `customer_notes` to `customer_note` when needed
- Copying legacy note data before removing old schema variants
- Ensuring `qs_services.price` is nullable
- Ensuring `qs_availability.max_bookings` is nullable

### Uninstall behavior

QuickSlot only removes its tables on uninstall when this option is enabled:

- `quickslot_delete_data_on_uninstall`

If that option is not truthy, uninstall leaves plugin data in place.

## Troubleshooting

### Activation fails

- Confirm WordPress is 6.0+
- Confirm PHP is 8.0+

### Booking concurrency protection warning

- Check the `qs_bookings` table engine
- Convert the table to InnoDB if needed

### Google Calendar settings show unavailable

- Confirm `vendor/autoload.php` exists
- Run Composer install for the plugin

### Tokens appear to expire and sync stops

- Reconnect Google Calendar if refresh fails
- Confirm OpenSSL support is present
- Re-test the connection from settings

### Reminders are delayed

- Check WP-Cron execution
- Prefer a real cron trigger in production
- If available in your stack, use Action Scheduler-backed execution

### Emails fail intermittently

- Move mail delivery to SMTP or a transactional provider
- Review the `qs_email_logs` table for patterns
