# Tiskerti

## Running steps

1. From the repository root, start all core services:
   - `docker compose up -d --build`
2. Wait for startup to finish.
   - The API is health-gated at boot, so first startup can take around 60-150 seconds before frontend data is ready.
3. Open the app and services:
   - Frontend: `http://localhost:5173`
   - API: `http://localhost:8000`
   - Nginx proxy: `http://localhost:8080`
   - API health check: `http://localhost:8080/api/health`
   - MailHog inbox: `http://localhost:8025`

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
