# QuickSlot

QuickSlot is a WordPress appointment booking plugin with a frontend multi-step booking flow, public REST endpoints, admin booking management, email notifications, reminder scheduling, and ICS calendar downloads.

## Current Features

- Frontend booking form via `[quickslot_booking]`
- Public REST endpoints for:
  - services
  - available dates
  - available time slots
  - booking creation
- Server-side slot validation before booking creation
- Admin management for:
  - services
  - availability
  - bookings list, filters, detail view, and status updates
  - CSV export
  - manual admin booking creation
  - settings for business details, email templates, and reminders
- Email system with:
  - template storage in options
  - placeholder replacement
  - send/fail logging
  - customer confirmation and cancellation emails
  - business notification emails
  - ICS attachment on confirmation emails
- Reminder scheduling with Action Scheduler support when available and WP-Cron fallback
- Token-protected ICS download link for customers

## Shortcode

Use the booking form on any page or post:

```text
[quickslot_booking]
```

## Admin Areas

- `QuickSlot > Services`
- `QuickSlot > Availability`
- `QuickSlot > Bookings`
- `QuickSlot > Settings`

## Notes

- Reminder scheduling currently uses a single `reminder_sent` flag per booking, so only one successful reminder is tracked even if multiple offsets are configured.
- Google Calendar OAuth and ICS email attachment management beyond confirmation emails are not implemented yet.
