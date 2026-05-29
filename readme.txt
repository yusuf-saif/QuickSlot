=== QuickSlot ===
Contributors: quickslot
Requires at least: 6.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Appointment booking for WordPress with frontend scheduling, admin booking management, reminders, email notifications, and ICS downloads.

== Description ==

QuickSlot provides a complete appointment booking flow for WordPress sites.

Current functionality includes:

- Frontend booking form with service, date, time, details, review, and success steps
- Public REST API endpoints for services, availability, and booking creation
- Server-side slot validation before bookings are saved
- Admin booking management with filters, detail view, status updates, CSV export, and manual booking creation
- Settings for business details, email templates, and reminder offsets
- Confirmation, notification, reminder, and cancellation email support
- Email logging to the plugin email log table
- ICS calendar invite generation, customer download links, and confirmation email attachments
- Reminder scheduling with Action Scheduler support when available and WP-Cron fallback

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it through the WordPress plugins screen.
2. Activate the plugin through the WordPress plugins screen.
3. Add the `[quickslot_booking]` shortcode to a page.
4. Configure services, availability, bookings, and settings from the QuickSlot admin menu.

== Usage ==

= Frontend Booking Form =

Add this shortcode to any page or post:

`[quickslot_booking]`

= Admin Pages =

- QuickSlot > Services
- QuickSlot > Availability
- QuickSlot > Bookings
- QuickSlot > Settings

== Features ==

- Service management
- Weekly availability and date exceptions
- Slot generation based on service duration and availability rules
- Customer booking creation with REST API validation
- Admin booking review and status changes
- Reminder scheduling and reminder email sending
- ICS file generation and download

== Changelog ==

= 1.0.0 =

- Initial functional plugin build with frontend booking flow, admin management, emails, reminders, and ICS support.
