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

## Quick start

1. Start the stack:
	- `docker compose up -d db api web nginx`
2. Install API dependencies and run migrations:
	- `docker compose run --rm api sh -lc "composer install && php bin/console doctrine:migrations:migrate --no-interaction"`
3. Install web dependencies (first run only):
	- `docker compose run --rm web npm install`
4. Open the app:
	- Frontend: `http://localhost:5173`
	- API: `http://localhost:8000`
	- Reverse proxy: `http://localhost:8080`

## Optional Mailhog (local email inbox)

Mailhog is optional, free, and very simple to run locally.

1. Start Mailhog profile:
	- `docker compose --profile mail up -d mailhog`
2. Open inbox UI:
	- `http://localhost:8025`
3. SMTP endpoint used by API:
	- host `mailhog`, port `1025`

If Mailhog is not running, reservation creation still succeeds; email delivery errors are written to `apps/api/var/share/mail-outbox/smtp-errors.log`.

## Demo seed script

The project includes a realistic demo data script:

- Script: `apps/api/scripts/seed_demo_data.sql`

Run it from the repository root:

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

## Auth note

For project simplicity in local mode, web tokens are stored in localStorage. This is acceptable for the university local demo scope and can be upgraded later to HttpOnly cookies.
