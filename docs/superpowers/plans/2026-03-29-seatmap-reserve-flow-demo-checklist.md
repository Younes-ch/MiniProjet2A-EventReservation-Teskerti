# Seat-Map Reserve Flow Demo Checklist (Phase 3)

Date: 2026-03-29
Scope: Local demo of seat selection, reservation confirmation, and ticket surfaces.

## 1. Pre-demo Setup

- [ ] Pull latest `main` branch.
- [ ] Start stack: `docker compose up -d db api web nginx`.
- [ ] Confirm API health is reachable.
- [ ] Open web app in browser.
- [ ] Ensure at least one published event exists with seats available.

## 2. Smoke Checks

- [ ] Event list page loads.
- [ ] Event detail page opens from a card click.
- [ ] Reservation page opens from event detail action.
- [ ] Seat map appears with legend, grid, and action toolbar.

## 3. Seat Map Interaction Demo

- [ ] Verify first available seat is pre-selected.
- [ ] Click a few available seats; selection summary updates.
- [ ] Attempt selecting above max (4) and verify helper/error feedback.
- [ ] Use `Select best 4` and verify summary shows four seats.
- [ ] Use `Reset selection` and verify fallback to first available seat.
- [ ] Click `Refresh availability` and verify toast + synced timestamp.

## 4. Reservation Submit Demo

- [ ] Fill name, email, and phone with valid values.
- [ ] Submit reservation and verify success navigation to confirmation.
- [ ] Confirm selected seats are shown on confirmation page.
- [ ] Open tickets page and verify latest ticket includes seat labels.

## 5. Error Path Demo

- [ ] Trigger a refresh failure condition (temporary API stop or offline simulation).
- [ ] Verify non-blocking refresh error toast appears.
- [ ] Confirm previous selected seats remain visible after failed refresh.

## 6. API Validation Points (Optional During Demo)

- [ ] `GET /api/events/{slug}/seats` returns layout + items.
- [ ] `POST /api/reservations` accepts `seat_labels` and returns them.
- [ ] Invalid seat labels return `seat_selection_invalid`.
- [ ] Already reserved seats return `seats_unavailable`.

## 7. Regression Checks

- [ ] Existing reservation modal still validates form fields correctly.
- [ ] Mobile layout keeps seat controls readable and tappable.
- [ ] Build and tests pass before closing demo preparation.

## 8. Commands Used for Verification

- Frontend lint + test + build:
  - `docker compose run --rm web sh -lc "npm run lint && npm run test && npm run build"`
- API targeted controller tests:
  - `docker compose run --rm api sh -lc "php bin/phpunit tests/Controller/PublicReservationsControllerTest.php tests/Controller/PublicEventsControllerTest.php"`
