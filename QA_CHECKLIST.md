# QuickSlot QA Checklist

Version under test: `1.0.1`  
DB version under test: `1.0.1`

## Instructions

- Record `Pass` or `Fail` for each test.
- Use the `Notes` field for screenshots, environment details, and observed defects.
- Run tests in a staging environment with WordPress 6.0+ and PHP 8.0+.
- Prefer InnoDB for realistic concurrency validation.

## Installation

### QS-INST-001

- Test ID: `QS-INST-001`
- Category: Installation
- Preconditions: Clean WordPress site meeting minimum requirements.
- Steps:
  1. Upload or install the plugin.
  2. Activate QuickSlot.
  3. Open WordPress admin.
- Expected Result: Plugin activates without fatal errors and the `QuickSlot` admin menu appears.
- Pass / Fail:
- Notes:

### QS-INST-002

- Test ID: `QS-INST-002`
- Category: Installation
- Preconditions: Plugin activated.
- Steps:
  1. Inspect the database.
  2. Confirm the six QuickSlot tables exist.
- Expected Result: `qs_services`, `qs_availability`, `qs_availability_exceptions`, `qs_bookings`, `qs_calendar_connections`, and `qs_email_logs` exist with the active WordPress prefix.
- Pass / Fail:
- Notes:

### QS-INST-003

- Test ID: `QS-INST-003`
- Category: Installation
- Preconditions: Plugin activated.
- Steps:
  1. Check stored options after activation.
  2. Read `quickslot_version` and `quickslot_db_version`.
- Expected Result: Both options are present and equal `1.0.1`.
- Pass / Fail:
- Notes:

## Migration

### QS-MIG-001

- Test ID: `QS-MIG-001`
- Category: Migration
- Preconditions: Site upgraded from an earlier QuickSlot schema with `customer_notes` or missing `customer_note`.
- Steps:
  1. Run the upgraded plugin.
  2. Trigger WordPress so plugin load hooks fire.
  3. Inspect `qs_bookings` schema and sample migrated data.
- Expected Result: `customer_note` exists, legacy note data is preserved, and obsolete `customer_notes` is removed if applicable.
- Pass / Fail:
- Notes:

### QS-MIG-002

- Test ID: `QS-MIG-002`
- Category: Migration
- Preconditions: Site upgraded from a schema where `qs_services.price` is non-nullable.
- Steps:
  1. Run the upgrade.
  2. Inspect the `price` column definition.
  3. Save a service with blank price.
- Expected Result: `price` is nullable and blank service prices save successfully.
- Pass / Fail:
- Notes:

### QS-MIG-003

- Test ID: `QS-MIG-003`
- Category: Migration
- Preconditions: Site upgraded from a schema where `qs_availability.max_bookings` is non-nullable.
- Steps:
  1. Run the upgrade.
  2. Inspect the `max_bookings` column definition.
  3. Save availability with blank max bookings.
- Expected Result: `max_bookings` is nullable and blank values save correctly.
- Pass / Fail:
- Notes:

### QS-MIG-004

- Test ID: `QS-MIG-004`
- Category: Migration
- Preconditions: Site running an older plugin build with real booking data.
- Steps:
  1. Upgrade to v1.0.1.
  2. Verify services, bookings, availability, and settings in admin.
  3. Perform a frontend booking.
- Expected Result: Existing data remains intact and the upgraded site behaves normally.
- Pass / Fail:
- Notes:

## Services

### QS-SVC-001

- Test ID: `QS-SVC-001`
- Category: Services
- Preconditions: Plugin activated.
- Steps:
  1. Create a new active service.
  2. Enter name, description, duration, and price.
  3. Save.
- Expected Result: Service saves successfully and appears in the Services list.
- Pass / Fail:
- Notes:

### QS-SVC-002

- Test ID: `QS-SVC-002`
- Category: Services
- Preconditions: At least one existing service.
- Steps:
  1. Edit the service.
  2. Change duration or status.
  3. Save.
- Expected Result: Changes persist and list output updates correctly.
- Pass / Fail:
- Notes:

### QS-SVC-003

- Test ID: `QS-SVC-003`
- Category: Services
- Preconditions: At least one active service.
- Steps:
  1. Deactivate the service.
  2. Load the frontend booking form.
- Expected Result: Deactivated services do not appear in the public service list.
- Pass / Fail:
- Notes:

## Availability

### QS-AVL-001

- Test ID: `QS-AVL-001`
- Category: Availability
- Preconditions: At least one active service.
- Steps:
  1. Configure weekly hours for one business day.
  2. Save availability.
  3. Request available dates and slots for that day.
- Expected Result: Future dates and slots are generated based on configured hours.
- Pass / Fail:
- Notes:

### QS-AVL-002

- Test ID: `QS-AVL-002`
- Category: Availability
- Preconditions: Weekly hours configured.
- Steps:
  1. Add one or more breaks.
  2. Save.
  3. Review generated slots around the break period.
- Expected Result: Slots overlapping the break are not returned.
- Pass / Fail:
- Notes:

### QS-AVL-003

- Test ID: `QS-AVL-003`
- Category: Availability
- Preconditions: Weekly hours configured.
- Steps:
  1. Set buffer minutes.
  2. Generate slots for a service.
- Expected Result: Slot spacing reflects service duration plus buffer.
- Pass / Fail:
- Notes:

### QS-AVL-004

- Test ID: `QS-AVL-004`
- Category: Availability
- Preconditions: Weekly hours configured.
- Steps:
  1. Set max bookings for a day.
  2. Create bookings until the cap is reached.
  3. Refresh availability.
- Expected Result: Once the booking count reaches the daily cap, no more slots are returned for that date.
- Pass / Fail:
- Notes:

### QS-AVL-005

- Test ID: `QS-AVL-005`
- Category: Availability
- Preconditions: Weekly hours configured.
- Steps:
  1. Create a blocked exception date.
  2. Request availability for that month and date.
- Expected Result: The blocked date is unavailable and no slots are returned.
- Pass / Fail:
- Notes:

### QS-AVL-006

- Test ID: `QS-AVL-006`
- Category: Availability
- Preconditions: Weekly hours configured.
- Steps:
  1. Create a custom-hours exception date.
  2. Request slots for that date.
- Expected Result: Slots reflect the custom exception hours rather than weekly defaults.
- Pass / Fail:
- Notes:

## Frontend Booking

### QS-FE-001

- Test ID: `QS-FE-001`
- Category: Frontend Booking
- Preconditions: Booking page contains `[quickslot_booking]` and at least one active service exists.
- Steps:
  1. Visit the booking page.
  2. Progress through service, date, time, details, review.
- Expected Result: Each step loads successfully and allows forward progression when valid.
- Pass / Fail:
- Notes:

### QS-FE-002

- Test ID: `QS-FE-002`
- Category: Frontend Booking
- Preconditions: Frontend booking page loaded.
- Steps:
  1. Leave required fields blank.
  2. Attempt to continue or submit.
- Expected Result: Required field validation appears for name and email.
- Pass / Fail:
- Notes:

### QS-FE-003

- Test ID: `QS-FE-003`
- Category: Frontend Booking
- Preconditions: Frontend booking page loaded.
- Steps:
  1. Complete a valid booking.
  2. Observe the success state.
  3. Inspect the created booking in admin.
- Expected Result: Booking is created, success screen shows booking ID, and an ICS download link is available.
- Pass / Fail:
- Notes:

### QS-FE-004

- Test ID: `QS-FE-004`
- Category: Frontend Booking
- Preconditions: Browser dev tools or API client available.
- Steps:
  1. Submit a booking with the honeypot `website` field populated.
- Expected Result: Request is ignored as spam and no real booking is created.
- Pass / Fail:
- Notes:

## Concurrency

### QS-CON-001

- Test ID: `QS-CON-001`
- Category: Concurrency
- Preconditions: InnoDB bookings table and one available slot.
- Steps:
  1. Submit two booking requests for the same service, date, and time simultaneously.
  2. Inspect results.
- Expected Result: Only one booking succeeds; the competing request returns slot-unavailable behavior.
- Pass / Fail:
- Notes:

### QS-CON-002

- Test ID: `QS-CON-002`
- Category: Concurrency
- Preconditions: Non-transactional bookings table engine available for testing.
- Steps:
  1. Repeat the same simultaneous booking test.
  2. Observe admin notices and outcomes.
- Expected Result: Admin warning about reduced double-booking protection appears; record actual concurrency behavior.
- Pass / Fail:
- Notes:

## Admin Bookings

### QS-ADM-001

- Test ID: `QS-ADM-001`
- Category: Admin Bookings
- Preconditions: At least one active service.
- Steps:
  1. Create a manual booking from `QuickSlot > Bookings > Add Booking`.
  2. Save as `Pending`.
- Expected Result: Booking is created successfully and appears in the bookings list with source `admin`.
- Pass / Fail:
- Notes:

### QS-ADM-002

- Test ID: `QS-ADM-002`
- Category: Admin Bookings
- Preconditions: Existing booking.
- Steps:
  1. Open the booking detail page.
  2. Change status from `Pending` to `Confirmed`.
- Expected Result: Status saves successfully and confirmation-side effects can run.
- Pass / Fail:
- Notes:

### QS-ADM-003

- Test ID: `QS-ADM-003`
- Category: Admin Bookings
- Preconditions: Existing booking data.
- Steps:
  1. Filter bookings by date range, service, and status.
  2. Use customer search.
- Expected Result: Filters and search return matching records only.
- Pass / Fail:
- Notes:

### QS-ADM-004

- Test ID: `QS-ADM-004`
- Category: Admin Bookings
- Preconditions: Existing booking data.
- Steps:
  1. Export bookings to CSV.
  2. Open the exported file.
- Expected Result: CSV downloads successfully and contains expected columns and data.
- Pass / Fail:
- Notes:

## Emails

### QS-EML-001

- Test ID: `QS-EML-001`
- Category: Emails
- Preconditions: Mail delivery configured.
- Steps:
  1. Create a frontend booking.
  2. Check customer mailbox.
  3. Check business mailbox.
- Expected Result: Customer receives confirmation email and business receives notification email.
- Pass / Fail:
- Notes:

### QS-EML-002

- Test ID: `QS-EML-002`
- Category: Emails
- Preconditions: Mail delivery configured and templates customized.
- Steps:
  1. Trigger each email type.
  2. Review email content.
- Expected Result: Placeholders resolve correctly and customized template content is used.
- Pass / Fail:
- Notes:

### QS-EML-003

- Test ID: `QS-EML-003`
- Category: Emails
- Preconditions: Mail delivery configured.
- Steps:
  1. Trigger an email send.
  2. Inspect `qs_email_logs`.
- Expected Result: A log record is written with the correct type, recipient, subject, and status.
- Pass / Fail:
- Notes:

## Reminders

### QS-REM-001

- Test ID: `QS-REM-001`
- Category: Reminders
- Preconditions: Reminder offsets configured and cron working.
- Steps:
  1. Create a booking far enough in the future.
  2. Inspect scheduled actions or cron events.
- Expected Result: Reminder events are scheduled for configured offsets.
- Pass / Fail:
- Notes:

### QS-REM-002

- Test ID: `QS-REM-002`
- Category: Reminders
- Preconditions: Reminder event due and mail delivery configured.
- Steps:
  1. Allow the reminder event to run.
  2. Check customer mailbox.
  3. Inspect the booking row.
- Expected Result: Reminder email sends and `reminder_sent` becomes `1`.
- Pass / Fail:
- Notes:

### QS-REM-003

- Test ID: `QS-REM-003`
- Category: Reminders
- Preconditions: Booking with scheduled reminders.
- Steps:
  1. Cancel the booking before reminders fire.
  2. Inspect scheduled tasks.
- Expected Result: Pending reminder schedules for that booking are removed.
- Pass / Fail:
- Notes:

### QS-REM-004

- Test ID: `QS-REM-004`
- Category: Reminders
- Preconditions: Multiple reminder offsets configured.
- Steps:
  1. Create a booking with enough lead time for all offsets.
  2. Observe reminder execution over time.
- Expected Result: Record actual behavior. In v1.0.1, only the first successful reminder should set `reminder_sent = 1`, preventing later reminders for that booking.
- Pass / Fail:
- Notes:

## ICS

### QS-ICS-001

- Test ID: `QS-ICS-001`
- Category: ICS
- Preconditions: Successful booking created.
- Steps:
  1. Use the ICS link from the success screen or confirmation email.
  2. Download the file.
- Expected Result: A valid `.ics` file downloads successfully.
- Pass / Fail:
- Notes:

### QS-ICS-002

- Test ID: `QS-ICS-002`
- Category: ICS
- Preconditions: Confirmation email enabled.
- Steps:
  1. Create a booking.
  2. Open the customer confirmation email.
- Expected Result: The email includes an ICS attachment.
- Pass / Fail:
- Notes:

### QS-ICS-003

- Test ID: `QS-ICS-003`
- Category: ICS
- Preconditions: Existing booking with valid ICS URL.
- Steps:
  1. Modify the token in the download URL.
  2. Request the file.
- Expected Result: Download is rejected with not-found behavior.
- Pass / Fail:
- Notes:

## Google Calendar

### QS-GCAL-001

- Test ID: `QS-GCAL-001`
- Category: Google Calendar
- Preconditions: Google API client installed and OAuth app configured.
- Steps:
  1. Save Google settings.
  2. Complete the connect flow.
  3. Test the connection.
- Expected Result: Connection succeeds and the settings page shows connected status.
- Pass / Fail:
- Notes:

### QS-GCAL-002

- Test ID: `QS-GCAL-002`
- Category: Google Calendar
- Preconditions: Connected Google Calendar and pending booking.
- Steps:
  1. Confirm the booking.
  2. Inspect the target Google Calendar.
  3. Inspect booking sync fields.
- Expected Result: Event is created in Google Calendar and `calendar_provider` plus `calendar_event_id` are stored on the booking.
- Pass / Fail:
- Notes:

### QS-GCAL-003

- Test ID: `QS-GCAL-003`
- Category: Google Calendar
- Preconditions: Booking already synced to Google Calendar.
- Steps:
  1. Cancel the booking.
  2. Inspect the Google Calendar event.
- Expected Result: QuickSlot attempts to remove the event from Google Calendar.
- Pass / Fail:
- Notes:

### QS-GCAL-004

- Test ID: `QS-GCAL-004`
- Category: Google Calendar
- Preconditions: Existing Google connection with expired access token and valid refresh token.
- Steps:
  1. Trigger a sync or connection test.
  2. Inspect connection behavior and updated token data.
- Expected Result: Access token refresh succeeds automatically and sync or test continues normally.
- Pass / Fail:
- Notes:

## Settings

### QS-SET-001

- Test ID: `QS-SET-001`
- Category: Settings
- Preconditions: QuickSlot activated.
- Steps:
  1. Save General settings with valid business name and email.
  2. Reload the page.
- Expected Result: Values persist correctly.
- Pass / Fail:
- Notes:

### QS-SET-002

- Test ID: `QS-SET-002`
- Category: Settings
- Preconditions: QuickSlot activated.
- Steps:
  1. Enter an invalid business email.
  2. Save General settings.
- Expected Result: Settings are rejected and an invalid email notice appears.
- Pass / Fail:
- Notes:

### QS-SET-003

- Test ID: `QS-SET-003`
- Category: Settings
- Preconditions: QuickSlot activated.
- Steps:
  1. Save reminder settings with no positive offsets.
- Expected Result: Save is rejected with an invalid offsets notice.
- Pass / Fail:
- Notes:

## Design System

### QS-DS-001

- Test ID: `QS-DS-001`
- Category: Design System
- Preconditions: Booking page available.
- Steps:
  1. Set custom primary and accent colors.
  2. Load the frontend booking form.
- Expected Result: Form uses custom color tokens on buttons, slot states, and accents.
- Pass / Fail:
- Notes:

### QS-DS-002

- Test ID: `QS-DS-002`
- Category: Design System
- Preconditions: Theme with supported color tokens.
- Steps:
  1. Enable `Use Site Design System`.
  2. Load the booking form and settings preview.
- Expected Result: QuickSlot inherits common site/theme color variables and preview updates correctly.
- Pass / Fail:
- Notes:

## Cache and CDN

### QS-CACHE-001

- Test ID: `QS-CACHE-001`
- Category: Cache/CDN
- Preconditions: Site-level page cache or CDN enabled.
- Steps:
  1. Load the booking page from cache.
  2. Create a booking for a visible slot.
  3. Refresh availability.
- Expected Result: REST-driven availability updates correctly and the booked slot no longer appears.
- Pass / Fail:
- Notes:

### QS-CACHE-002

- Test ID: `QS-CACHE-002`
- Category: Cache/CDN
- Preconditions: Cache/CDN enabled.
- Steps:
  1. Update services or availability.
  2. Clear or bypass cache as needed.
  3. Reload the frontend form.
- Expected Result: Updated service or availability data is reflected correctly.
- Pass / Fail:
- Notes:

## Security

### QS-SEC-001

- Test ID: `QS-SEC-001`
- Category: Security
- Preconditions: Public site.
- Steps:
  1. Submit more than 10 booking attempts within 15 minutes using complete required fields.
- Expected Result: Later attempts are rate-limited with HTTP 429 behavior.
- Pass / Fail:
- Notes:

### QS-SEC-002

- Test ID: `QS-SEC-002`
- Category: Security
- Preconditions: Non-admin user account.
- Steps:
  1. Attempt to access QuickSlot admin pages.
- Expected Result: Access is denied because QuickSlot admin pages require `manage_options`.
- Pass / Fail:
- Notes:

### QS-SEC-003

- Test ID: `QS-SEC-003`
- Category: Security
- Preconditions: Booking page and API client available.
- Steps:
  1. Submit malformed or invalid REST payload values.
  2. Inspect API responses.
- Expected Result: Invalid input is rejected cleanly without fatal errors or unsafe output.
- Pass / Fail:
- Notes:

## Timezone Testing

### QS-TZ-001

- Test ID: `QS-TZ-001`
- Category: Timezone testing
- Preconditions: Site timezone set and browser in a different timezone.
- Steps:
  1. Load the frontend form from a client in another timezone.
  2. Create a booking.
  3. Inspect stored booking fields and admin display.
- Expected Result: Availability follows the site timezone, booking UTC timestamps are stored correctly, and the submitted client timezone is captured in the booking row.
- Pass / Fail:
- Notes:

## Upgrade Path Validation

### QS-UPG-001

- Test ID: `QS-UPG-001`
- Category: Upgrade path validation
- Preconditions: Working pre-1.0.1 site with real data and settings.
- Steps:
  1. Back up the site.
  2. Upgrade to v1.0.1.
  3. Validate core user journeys after upgrade.
- Expected Result: Upgrade completes cleanly, version options update to `1.0.1`, and all core flows still work.
- Pass / Fail:
- Notes:
