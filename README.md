# Tiskerti

## Running steps

1. From the repository root, start all core services:
   - `docker compose up -d --build`
2. Wait for startup to finish.
   - Containers are health-gated at boot, so first startup can take around 3 minutes before everything is ready.
   - You can monitor progress with `docker compose ps` and wait until `api` and `web` show healthy status.
3. Open the app and services:
   - Frontend: `http://localhost:5173`
   - API: `http://localhost:8000`
   - Nginx proxy: `http://localhost:8080`
   - API health check: `http://localhost:8080/api/health`
   - MailHog inbox: `http://localhost:8025`

## Testing workflow

1. Start services and wait for health checks:
   - `docker compose up -d --build`
   - `docker compose ps`
2. Verify API and frontend reachability:
   - Open `http://localhost:8080/api/health` and confirm a healthy response.
   - Open `http://localhost:8080` and confirm the app shell loads.
3. Manual reservation flow check:
   - Reserve an event seat from the web UI.
   - Confirm the confirmation page can download both the PDF ticket and calendar `.ics`.
   - Open MailHog (`http://localhost:8025`) and verify email ticket/calendar links use `http://localhost:8080` and download correctly.
4. Admin workflow check:
   - Open `http://localhost:8080/login`.
   - Sign in with the demo admin account: `alex@example.com / Passw0rd!2026`.
   - Open `http://localhost:8080/admin` and verify the dashboard loads events and reservations.
   - In the reservations area, run a status update (for example Confirmed -> Cancelled -> Confirmed) and verify the UI refreshes.
5. Passkey workflow check:
   - While signed in as admin, in the admin header click `Enroll Passkey` and complete the browser passkey prompt.
   - In `Passkey Security`, toggle the policy to require passkey after password login.
   - Sign out, sign in again with the demo account, and verify the login requires a passkey verification step.
   - After verification, confirm admin actions still work (for example updating reservation status).
6. Optional clean reset before re-running checks:
   - `docker compose down -v`
   - `docker compose up -d --build`

## MailHog SMTP settings

1. MailHog now starts automatically with `docker compose up`.
2. SMTP delivery is enabled by default (`SMTP_ENABLED=1`).
3. SMTP endpoint used by the API:
   - host: `mailhog`
   - port: `1025`

## Stop and reset

1. Stop containers:
   - `docker compose down`
2. Full reset (remove DB volume, then recreate):
   - `docker compose down -v`
   - `docker compose up -d --build`
