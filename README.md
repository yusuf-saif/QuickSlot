# QuickSlot v1.0.1

QuickSlot is a WordPress appointment booking plugin for service businesses that need a simple booking flow, internal booking management, reminder emails, calendar invites, and Google Calendar sync.

It is designed for three audiences:

- WordPress site owners who want a booking page without a SaaS platform
- Agencies implementing service booking for clients
- Developers extending or integrating the plugin

## Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Shortcodes](#shortcodes)
- [Google Calendar Setup](#google-calendar-setup)
- [FAQ](#faq)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)

## Overview

QuickSlot provides a full booking workflow inside WordPress:

- Create services with duration and optional pricing
- Define weekly availability and date-based exceptions
- Publish a frontend booking form with a shortcode
- Accept and manage bookings in wp-admin
- Notify customers and staff by email
- Send calendar invites and reminder emails
- Export bookings to CSV
- Sync confirmed bookings to Google Calendar
- Adjust frontend colors to match your site

Bookings are stored in dedicated plugin tables, slot availability is validated server-side, and public booking actions are exposed through WordPress REST API endpoints.

## Features

| Feature | What it does |
| --- | --- |
| Services | Create services with name, description, duration, price, paid/free flag, and active status |
| Availability | Configure weekly business hours, breaks, buffers, and optional max bookings per day |
| Frontend booking form | Multi-step booking form published with `[quickslot_booking]` |
| Admin bookings | View, filter, create, and update bookings from wp-admin |
| CSV export | Export bookings from the admin Bookings screen |
| Email notifications | Send customer and business emails on booking creation |
| Reminders | Schedule reminder emails with configurable offsets |
| ICS calendar invites | Generate downloadable `.ics` calendar invites and attach them to booking emails |
| Google Calendar sync | Sync confirmed bookings to Google Calendar and remove synced events when cancelled |
| Design customization | Use QuickSlot colors or inherit site/theme design tokens |

## Requirements

| Requirement | Minimum | Recommendation |
| --- | --- | --- |
| WordPress | 6.0 | Latest stable release |
| PHP | 8.0 | PHP 8.1+ |
| Database | WordPress-supported MySQL/MariaDB | MySQL 5.7+ or MariaDB 10.4+ |
| Table engine | Not enforced | InnoDB strongly recommended |

### Why InnoDB matters

QuickSlot uses transaction-aware booking protection when the bookings table engine supports transactions. InnoDB gives the strongest protection against overlapping bookings during concurrent requests. On non-transactional engines such as MyISAM, QuickSlot still performs final server-side slot checks, but concurrency protection is reduced.

### Google Calendar dependency

Google Calendar integration requires the Google API client library listed in `composer.json`:

- `google/apiclient ^2.15`

If the library is missing, the Google Calendar settings screen remains available, but connection and sync will not work until dependencies are installed.

## Installation

1. Upload the plugin to `/wp-content/plugins/quickslot/` or install it through the WordPress plugins screen.
2. Activate the plugin from `Plugins` in wp-admin.
3. Confirm your site meets the minimum requirements:
   - WordPress 6.0+
   - PHP 8.0+
4. Open `QuickSlot` in wp-admin.
5. Create at least one service in `QuickSlot > Services`.
6. Configure weekly hours and exceptions in `QuickSlot > Availability`.
7. Review `QuickSlot > Settings`:
   - General Settings
   - Email Templates
   - Calendar
   - Colors
   - Reminders
8. Create or edit a page and add the shortcode `[quickslot_booking]`.
9. Publish the page and test the full booking flow.

## Quick Start

### 1. Create a service

Go to `QuickSlot > Services` and click `Add New Service`.

Recommended fields:

- Service Name
- Description
- Duration in minutes
- Price, if applicable
- Paid or free
- Active status

### 2. Configure availability

Go to `QuickSlot > Availability` and define:

- Weekly working hours
- Break windows
- Buffer minutes between appointments
- Optional daily booking caps
- Date-specific exceptions for closures or custom hours

### 3. Add the shortcode

Add this shortcode to any page or post:

```text
[quickslot_booking]
```

### 4. Test a booking

Open the booking page as a visitor and complete a test booking.

Verify:

- Services load
- Available dates appear
- Available time slots appear
- Booking submission succeeds
- A booking is created in `QuickSlot > Bookings`
- Email notifications are sent
- The success screen displays a calendar invite download link

## Shortcodes

### `[quickslot_booking]`

Displays the frontend multi-step booking form.

What it includes:

- Service selection
- Date selection
- Time slot selection
- Customer details
- Review step
- Success state with booking ID and ICS download link

## Google Calendar Setup

QuickSlot includes built-in Google Calendar OAuth support in `QuickSlot > Settings > Calendar`.

High-level setup:

1. Create a Google Cloud project.
2. Enable the Google Calendar API.
3. Create OAuth credentials for a web application.
4. Copy the `Client ID` and `Client Secret` into QuickSlot.
5. Copy the QuickSlot redirect URI into the Google OAuth app.
6. Save the calendar settings.
7. Click `Connect Google Calendar`.
8. Approve access to the target Google account.
9. Optionally test the connection from the Calendar settings tab.

Important behavior:

- QuickSlot syncs confirmed bookings to Google Calendar.
- Frontend bookings are created with a `pending` status by default.
- A booking syncs when it becomes `confirmed`.
- If a synced booking is later cancelled, QuickSlot attempts to delete the Google Calendar event.

## FAQ

### Does QuickSlot work with any theme?

Yes. The booking form loads its own CSS and also supports inheriting common theme color tokens.

### Can I add the booking form to multiple pages?

Yes. You can place `[quickslot_booking]` on any page or post.

### Are booking slots validated on the server?

Yes. QuickSlot validates the slot again before creating the booking to prevent stale frontend availability.

### Does QuickSlot support admin-created bookings?

Yes. Admins can create bookings manually from `QuickSlot > Bookings > Add Booking`.

### Does QuickSlot send calendar invites?

Yes. QuickSlot generates `.ics` files and provides a token-protected download URL. Confirmation emails also attach an ICS file.

### Can I export bookings?

Yes. Use the `Export CSV` action on the Bookings screen.

### Does Google Calendar sync require Composer dependencies?

Yes. The Google API client library must be installed for OAuth and sync to work.

### Can I configure multiple reminder offsets?

You can configure multiple reminder offsets in settings. In v1.0.1, QuickSlot stores a single `reminder_sent` flag per booking, so only the first successful reminder is tracked and later configured reminders for the same booking will not send after that flag is set.

## Troubleshooting

### Services or slots do not appear

- Confirm at least one service is `Active`
- Confirm weekly availability is enabled for the relevant day
- Check exception rules for blocked dates
- Confirm the service duration fits within the available window
- Clear site/page cache or CDN cache if the booking page is heavily cached

### Double-booking warning appears in admin

QuickSlot shows an admin notice when the bookings table engine does not support transactions. Move the site database tables to InnoDB for stronger concurrency protection.

### Google Calendar cannot connect

- Verify `Client ID`, `Client Secret`, and redirect URI
- Confirm the Google Calendar API is enabled in Google Cloud
- Confirm the plugin dependencies are installed
- Use the built-in connection test after authenticating

### Emails are not arriving

- Confirm `Business Email` is valid in `Settings > General`
- Test site email delivery with SMTP
- Review your host mail configuration
- Check the `qs_email_logs` table for send/fail records

### Reminders are not sending

- Confirm reminder offsets are saved
- Confirm WP-Cron is working on the site
- If available, prefer Action Scheduler support
- Check whether the booking already has `reminder_sent = 1`

### ICS downloads return not found

- Confirm the booking still exists
- Confirm the download URL token is intact
- Regenerate the booking flow if the link was copied incompletely

## Changelog

### 1.0.1

- Added ordered migration handling for database upgrades
- Standardized the `customer_note` booking column
- Ensured service `price` remains nullable
- Ensured `max_bookings` supports blank or unlimited daily limits
- Includes frontend booking flow, admin booking management, CSV export, emails, reminders, ICS invites, Google Calendar sync, and design settings
