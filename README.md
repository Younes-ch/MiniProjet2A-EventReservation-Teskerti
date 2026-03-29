# Tiskerti

Local-first event reservation platform for the Mini Projet 2A showcase.

## Stack

- Frontend: React + Vite + TypeScript
- Backend: Symfony API (PHP 8.2+)
- Database: PostgreSQL
- Auth: JWT + refresh + passkey support
- Infra: Docker Compose (web, api, db, nginx, optional mailhog)

## Repository layout

- `apps/api`: Symfony API and tests
- `apps/web`: React app
- `docker`: Nginx/PHP container files
- `docs/superpowers`: specs and demo plans

## Quick start (one command)

From repository root:

- `docker compose up -d --build`

That command is enough for local setup. On startup, containers automatically:

- install dependencies (`composer install`, `npm install`)
- wait for PostgreSQL readiness
- run API migrations
- seed demo data from `apps/api/scripts/seed_demo_data.sql`
- start API, frontend, and nginx

Note: the API container runs install + migration + seed on boot, so the first startup can take around 30-90 seconds. If `http://localhost:8080/api/health` returns `502` right after startup, wait a bit and retry.

Open:

- Frontend: `http://localhost:5173`
- API: `http://localhost:8000`
- Reverse proxy: `http://localhost:8080`

To reset from scratch (fresh DB volume + reseed):

- `docker compose down -v`
- `docker compose up -d --build`

## Optional Mailhog (local email inbox)

Mailhog is optional, free, and simple to run locally.

By default, SMTP sending is disabled for frictionless startup (`SMTP_ENABLED=0` in compose env defaults).

Enable Mailhog + SMTP delivery:

- `SMTP_ENABLED=1 docker compose --profile mail up -d`

Open inbox UI:

- `http://localhost:8025`

SMTP endpoint used by API:

- host `mailhog`, port `1025`

If SMTP is disabled or Mailhog is not running, reservation flow still succeeds and email content is still captured in local outbox files under `apps/api/var/share/mail-outbox`.

## Demo seed script

The project includes a deterministic demo seed script:

- Script: `apps/api/scripts/seed_demo_data.sql`

It now covers multiple outcomes, including:

- events with many seats available
- almost-full event
- sold-out events
- confirmed / cancelled / waitlisted reservations
- checked-in reservations (qr_scan and manual)
- seat status variations (`available`, `booked`, `blocked`, `held`)

Manual reseed (without restarting all containers):

- `Get-Content apps/api/scripts/seed_demo_data.sql | docker compose exec -T db psql -U tiskerti -d tiskerti`

## Feature highlights

- Public event listing/detail with venue map
- Seat-map reservation flow with seat validation
- QR token ticket generation + branded PDF download
- ICS calendar ticket export
- Waitlist enrollment for sold-out events
- Admin event CRUD
- Admin reservation supervision + QR check-in
- Admin analytics overview endpoint
- Local email notifications (Mailhog-compatible SMTP + local outbox)

## Useful endpoints

- `GET /api/events`
- `GET /api/events/{slug}`
- `GET /api/events/{slug}/seats`
- `POST /api/reservations`
- `POST /api/reservations/waitlist`
- `GET /api/reservations/{reservationId}/ticket.pdf?token=...`
- `GET /api/reservations/{reservationId}/calendar.ics?token=...`
- `GET /api/admin/events`
- `GET /api/admin/reservations`
- `POST /api/admin/reservations/check-in`
- `GET /api/admin/analytics/overview`

## Validation commands

- Frontend lint/test/build:
  - `docker compose run --rm web sh -lc "npm run lint && npm run test && npm run build"`
- Backend controller tests:
  - `docker compose run --rm api sh -lc "php bin/phpunit tests/Controller"`

## Manual workflow testing

Use the complete test checklist here:

- `docs/superpowers/plans/2026-03-29-complete-app-workflow-test-checklist.md`

## Auth note

For project simplicity in local mode, web tokens are stored in localStorage. This is acceptable for the university local demo scope and can be upgraded later to HttpOnly cookies.
