# QuickSlot Release Checklist

Version: `1.0.1`

## Code Review

- [ ] Review all code changes included in the release.
- [ ] Confirm no unintended debug code, logging noise, or dead code remains.
- [ ] Confirm version constants and release metadata match `1.0.1`.
- [ ] Confirm no plugin code was changed outside the intended release scope.

## Security Review

- [ ] Review public REST endpoints for validation and expected responses.
- [ ] Confirm capability checks protect admin pages and actions.
- [ ] Confirm nonce checks exist for admin state-changing actions.
- [ ] Confirm ICS download token protection still works.
- [ ] Confirm Google token encryption/decryption behavior remains intact.

## Migration Testing

- [ ] Test clean install on a fresh site.
- [ ] Test upgrade from a pre-1.0.1 build.
- [ ] Confirm `quickslot_version` updates to `1.0.1`.
- [ ] Confirm `quickslot_db_version` updates to `1.0.1`.
- [ ] Validate `customer_note` migration behavior.
- [ ] Validate nullable `price` behavior.
- [ ] Validate nullable `max_bookings` behavior.

## Google Calendar Testing

- [ ] Confirm `google/apiclient` is installed in the release artifact if required.
- [ ] Complete OAuth connect flow successfully.
- [ ] Run the built-in connection test successfully.
- [ ] Confirm a booking synced on `confirmed` status creates a calendar event.
- [ ] Confirm cancelling a synced booking attempts to remove the event.
- [ ] Confirm expired access token refresh works with a valid refresh token.

## Email Testing

- [ ] Test customer confirmation email.
- [ ] Test business notification email.
- [ ] Test cancellation email.
- [ ] Confirm placeholder replacement in all templates.
- [ ] Confirm email log entries are written correctly.
- [ ] Confirm ICS attachment is present on confirmation email.

## Reminder Testing

- [ ] Confirm reminder offsets save correctly.
- [ ] Confirm reminders schedule through Action Scheduler when available.
- [ ] Confirm reminders fall back to WP-Cron when Action Scheduler is unavailable.
- [ ] Confirm reminder emails send successfully.
- [ ] Confirm cancelled/completed/no-show bookings do not receive reminders.
- [ ] Validate current single-flag reminder behavior with multiple configured offsets.

## Cache and CDN Testing

- [ ] Test frontend booking flow behind page cache.
- [ ] Confirm service and availability updates appear after cache refresh.
- [ ] Confirm stale cached pages do not allow successful double booking.

## Performance Testing

- [ ] Test service list endpoint response time.
- [ ] Test date and slot endpoint response time on a realistic data set.
- [ ] Confirm booking submission remains responsive under repeated requests.
- [ ] Confirm admin bookings list and CSV export behave acceptably with larger datasets.

## Documentation Review

- [ ] README reflects the actual feature set and known limitations.
- [ ] USER_GUIDE matches current admin labels and workflows.
- [ ] ADMIN_GUIDE matches current migration and version behavior.
- [ ] DEVELOPER_GUIDE matches current routes, hooks, and schema.
- [ ] QA_CHECKLIST covers release-critical paths.

## Final Sign-off

- [ ] Product owner approves release contents.
- [ ] Engineering approves release readiness.
- [ ] QA approves test completion.
- [ ] Release artifact has been reviewed.
- [ ] Rollback and backup plan is confirmed.
- [ ] Release is approved for deployment.
