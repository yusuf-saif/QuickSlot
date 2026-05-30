# QuickSlot User Guide

Audience: business owners, office managers, and administrators using QuickSlot day to day.

## Contents

- [Getting Started](#getting-started)
- [Creating Services](#creating-services)
- [Configuring Availability](#configuring-availability)
- [Managing Exceptions](#managing-exceptions)
- [Accepting Bookings](#accepting-bookings)
- [Managing Bookings](#managing-bookings)
- [Booking Statuses](#booking-statuses)
- [Email Templates](#email-templates)
- [Reminder Settings](#reminder-settings)
- [Google Calendar Integration](#google-calendar-integration)
- [Design Settings](#design-settings)
- [Common Problems](#common-problems)
- [Best Practices](#best-practices)

## Getting Started

After QuickSlot is activated, you will manage it from the `QuickSlot` menu in WordPress admin.

Main areas:

- `QuickSlot > Services`
- `QuickSlot > Availability`
- `QuickSlot > Bookings`
- `QuickSlot > Settings`

Recommended first-time setup order:

1. Add your business name and email in `Settings > General`.
2. Create one or more services.
3. Configure weekly working hours.
4. Add any blocked dates or special hours.
5. Add `[quickslot_booking]` to a booking page.
6. Submit a test booking.

[SCREENSHOT: QuickSlot Admin Menu]

## Creating Services

Services are the appointment types customers can book.

To create a service:

1. Go to `QuickSlot > Services`.
2. Click `Add New Service`.
3. Complete the form.
4. Click `Save Service`.

Fields:

- `Service Name`: What customers see
- `Description`: Extra details about the service
- `Duration in Minutes`: Length of the appointment
- `Price`: Optional price value
- `Is Paid`: Marks whether the service is paid
- `Status`: `Active` or `Inactive`

Tips:

- Keep service names short and clear.
- Use inactive status instead of deleting services you may need again.
- Make sure service duration matches the real appointment length.

[SCREENSHOT: Services Page]

## Configuring Availability

Availability controls when customers can book.

Go to `QuickSlot > Availability` to configure:

- Weekly hours for each day
- Breaks inside the day
- Buffer time between appointments
- Optional maximum bookings per day

How to set weekly hours:

1. Enable availability for a day.
2. Set start and end times.
3. Add breaks if needed.
4. Set buffer minutes if you need time between appointments.
5. Enter `Max Bookings` only if you want a daily cap.
6. Save changes.

Examples:

- Monday: 09:00 to 17:00
- Lunch break: 12:00 to 13:00
- Buffer: 15 minutes
- Max bookings: blank for unlimited within available slots

Important notes:

- A blank `Max Bookings` value means no daily cap.
- Slot times are generated from the service duration plus buffer time.
- Same-day past times are not shown as bookable.

[SCREENSHOT: Weekly Availability Settings]

## Managing Exceptions

Exceptions override weekly availability for a specific date.

Use them for:

- Holidays
- Office closures
- Special hours
- Shortened schedules

Exception types:

- `Blocked`: The whole date is unavailable
- `Custom`: The date uses custom start and end times

To add an exception:

1. Go to `QuickSlot > Availability`.
2. Open the exceptions section.
3. Choose the date.
4. Select `Blocked` or `Custom`.
5. If using `Custom`, enter start and end times.
6. Add a reason if helpful.
7. Save.

[SCREENSHOT: Availability Exceptions]

## Accepting Bookings

The customer-facing booking form is displayed using this shortcode:

```text
[quickslot_booking]
```

Typical booking flow:

1. Customer selects a service.
2. Customer selects an available date.
3. Customer selects an available time slot.
4. Customer enters their contact details.
5. Customer reviews the booking.
6. Customer submits the booking.

After a successful booking:

- The booking is created in QuickSlot
- The customer sees a success message with a booking ID
- The customer can download an ICS calendar invite
- QuickSlot sends booking emails

Important status note:

- Frontend bookings are created as `Pending` by default
- You can later confirm them from the admin area

[SCREENSHOT: Frontend Booking Form]

## Managing Bookings

Go to `QuickSlot > Bookings` to manage appointments.

From the Bookings screen you can:

- Filter by date range
- Filter by service
- Filter by status
- Search by customer name or email
- Open a booking detail page
- Export results to CSV
- Add a booking manually

From a booking detail page you can:

- Review customer and service details
- Review date, time, timezone, and source
- Change the booking status

Admin-created bookings:

1. Click `Add Booking`
2. Choose a service
3. Enter date and time
4. Enter customer details
5. Choose `Pending` or `Confirmed`
6. Save

[SCREENSHOT: Bookings List]

[SCREENSHOT: Booking Detail Page]

## Booking Statuses

QuickSlot uses these booking statuses:

| Status | Meaning |
| --- | --- |
| Pending | Booking has been created but is not yet confirmed |
| Confirmed | Booking is approved and eligible for Google Calendar sync |
| Cancelled | Booking is cancelled |
| Completed | Booking has already happened and is complete |
| No-show | Customer did not attend |

Operational notes:

- Google Calendar sync is triggered when a booking becomes `Confirmed`
- Cancelling a synced booking makes QuickSlot attempt to remove the Google Calendar event
- Cancelled, completed, and no-show bookings do not receive reminders

## Email Templates

Manage email content in `QuickSlot > Settings > Email Templates`.

Available template types:

- Confirmation
- Notification
- Reminder
- Cancellation

Supported placeholders:

- `{customer_name}`
- `{customer_email}`
- `{customer_phone}`
- `{service_name}`
- `{service_duration}`
- `{service_price}`
- `{booking_date}`
- `{booking_time}`
- `{booking_id}`
- `{business_name}`
- `{business_email}`
- `{site_url}`
- `{cancellation_reason}`

Tips:

- Keep subject lines short.
- Include the booking date and time in customer-facing templates.
- Add your business name and reply details clearly.

[SCREENSHOT: Email Templates Settings]

## Reminder Settings

Manage reminders in `QuickSlot > Settings > Reminders`.

You can add one or more reminder offsets in hours before the booking, for example:

- `24`
- `2`
- `0.5`

Important current behavior in v1.0.1:

- QuickSlot stores a single `reminder_sent` flag per booking
- After one reminder sends successfully, later configured reminders for that same booking are not sent

This means you can configure multiple reminder offsets, but only the first successful reminder is tracked and sent per booking in the current version.

[SCREENSHOT: Reminder Settings]

## Google Calendar Integration

Set up Google Calendar in `QuickSlot > Settings > Calendar`.

What you need:

- Google Cloud project
- Google Calendar API enabled
- OAuth Client ID and Client Secret
- Authorized redirect URI copied from QuickSlot

Basic setup:

1. Enter your Google credentials.
2. Save settings.
3. Click `Connect Google Calendar`.
4. Complete the Google consent flow.
5. Use `Test Google Calendar Connection` to verify access.

How sync works:

- Confirmed bookings are pushed to Google Calendar
- Cancelled synced bookings are removed when possible
- QuickSlot stores token data securely in the calendar connections table

[SCREENSHOT: Google Calendar Settings]

## Design Settings

Open `QuickSlot > Settings > Colors` to adjust the visual styling.

Options:

- `Use Site Design System`: inherit common theme colors and tokens
- `Primary Color`
- `Accent Color`

Use the preview panel to review how buttons, slots, badges, and service cards will look.

[SCREENSHOT: Design Settings]

## Common Problems

### No services appear on the booking page

- Ensure at least one service is active
- Confirm the page contains `[quickslot_booking]`
- Clear page cache if needed

### No dates or slots are available

- Review weekly availability
- Check date exceptions
- Confirm the service duration fits inside the configured hours
- Check whether breaks or buffers are reducing available slots

### Customer did not receive email

- Verify the booking email address
- Verify the business email setting
- Configure SMTP on the site if mail delivery is unreliable

### Google Calendar is not syncing

- Confirm the booking status is `Confirmed`
- Re-test the Google connection
- Confirm the Google API client library is installed

### Reminders are not sending

- Confirm WP-Cron is active
- Confirm the booking is not cancelled, completed, or no-show
- Check whether one reminder has already been marked as sent

## Best Practices

- Use InnoDB tables for stronger double-booking protection.
- Keep service durations realistic and consistent.
- Add buffer time when staff need turnover time between bookings.
- Block holidays and known closure dates in advance.
- Use a valid business email and SMTP for reliable email delivery.
- Confirm test bookings before launching publicly.
- Re-test Google Calendar sync after changing credentials.
- Export bookings regularly as part of your reporting workflow.
