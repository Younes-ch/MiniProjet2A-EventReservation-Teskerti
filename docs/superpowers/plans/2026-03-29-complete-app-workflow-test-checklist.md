# Complete App Workflow Test Checklist

Date: 2026-03-29
Scope: End-to-end manual verification for public, auth, ticketing, waitlist, and admin workflows.

## 1. Environment preflight

- [ ] Run `docker compose up -d --build`.
- [ ] Confirm containers are healthy/running (`db`, `api`, `web`, `nginx`).
- [ ] Open frontend (`http://localhost:5173`) and verify home loads.
- [ ] Confirm API health endpoint responds.
- [ ] Confirm seeded events are visible (including at least one sold-out event).

## 2. Public event discovery flow

- [ ] Open event list and verify cards render title/date/location/price.
- [ ] Open at least 3 different event detail pages.
- [ ] Verify venue map section renders for each detail page.
- [ ] Verify external directions link opens correctly.

## 3. Public reservation success flow

- [ ] Open reserve page for an event with availability.
- [ ] Verify seat map appears with available/reserved legend.
- [ ] Use seat actions: `Select best 4`, then `Reset selection`.
- [ ] Fill attendee form with valid name/email/phone.
- [ ] Submit reservation and verify confirmation page loads.
- [ ] Verify confirmation page shows reservation ID, QR token, and selected seats.
- [ ] Open tickets page and verify latest ticket details are present.

## 4. Seat-map validation and error flow

- [ ] Attempt more than max seats and verify validation message appears.
- [ ] Trigger stale seat scenario (reserve same seat in another session) and verify `seats_unavailable` behavior.
- [ ] Use refresh availability and verify success toast.
- [ ] Simulate refresh failure (temporary API interruption) and verify non-blocking error toast.

## 5. Sold-out and waitlist flow

- [ ] Select a sold-out event.
- [ ] Submit via waitlist path (`/api/reservations/waitlist` or `waitlist_if_full` behavior).
- [ ] Verify API returns `status=waitlisted` and `waitlist_position`.
- [ ] Verify waitlisted reservation appears in admin reservation list.

## 6. Ticket export workflow

- [ ] From a confirmed reservation, download PDF ticket.
- [ ] Verify PDF opens and includes branded header and reservation details.
- [ ] Download ICS calendar file for the same reservation.
- [ ] Import/open ICS file and verify title/date/location are correct.
- [ ] Try download with invalid token and verify error response.

## 7. Authentication and session workflow

- [ ] Login with password and verify access token session works.
- [ ] Verify protected admin pages are inaccessible without auth.
- [ ] Verify logout clears session and redirects appropriately.
- [ ] Verify refresh-based continuation after token expiry window (if applicable during run).

## 8. Passkey workflow (if enabled in local browser)

- [ ] Start passkey registration from admin/account flow.
- [ ] Complete registration and verify credential appears in list.
- [ ] Rename credential and verify update.
- [ ] Revoke credential and verify removal.
- [ ] Verify passkey-required admin policy behavior remains coherent.

## 9. Admin event CRUD workflow

- [ ] Open admin events list.
- [ ] Create a new event and verify it appears in list and public catalog.
- [ ] Edit event fields (title/date/price/seats/tone) and verify updates persist.
- [ ] Delete event and verify removal from admin and public list.

## 10. Admin reservation queue workflow

- [ ] Open reservation queue and verify pagination metadata.
- [ ] Filter by status: all/confirmed/cancelled/waitlisted.
- [ ] Filter by specific event slug.
- [ ] Search by attendee name/email/event title.
- [ ] Toggle reservation status confirmed <-> cancelled.
- [ ] Reopen cancelled reservation and verify state update.

## 11. Admin check-in workflow

- [ ] Check in a confirmed reservation with valid reservation code + QR token.
- [ ] Verify reservation shows checked-in timestamp.
- [ ] Try check-in with invalid token and verify proper error.
- [ ] Try second check-in of same reservation and verify already-checked-in error.
- [ ] Try check-in of non-confirmed reservation and verify rejection.

## 12. Admin analytics workflow

- [ ] Call `GET /api/admin/analytics/overview` with admin token.
- [ ] Verify totals include events/reservations/confirmed/cancelled/waitlisted/checked_in.
- [ ] Verify `top_events` is populated and sorted by reservation activity.
- [ ] Verify revenue estimate is returned and numeric.

## 13. Email workflow (optional Mailhog)

- [ ] Start with `SMTP_ENABLED=1 docker compose --profile mail up -d`.
- [ ] Create confirmed reservation and verify confirmation email in Mailhog UI (`http://localhost:8025`).
- [ ] Create waitlist reservation and verify waitlist email in Mailhog UI.
- [ ] Stop Mailhog and create reservation again; verify app still succeeds.
- [ ] Verify local outbox/error files are written under `apps/api/var/share/mail-outbox`.

## 14. Regression/smoke before demo

- [ ] `docker compose run --rm web sh -lc "npm run lint && npm run test && npm run build"`
- [ ] `docker compose run --rm api sh -lc "php bin/phpunit tests/Controller"`
- [ ] `docker compose config`
- [ ] Confirm no unexpected local changes remain before packaging demo.
