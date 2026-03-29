# Tiskerti

## Running steps

1. From the repository root, start all core services:
  - `docker compose up -d --build`
2. Wait for startup to finish (first run can take around 30-90 seconds).
3. Open the app and services:
  - Frontend: `http://localhost:5173`
  - API: `http://localhost:8000`
  - Nginx proxy: `http://localhost:8080`
  - API health check: `http://localhost:8080/api/health`

## Running steps with MailHog

1. Enable SMTP and start the MailHog profile.
2. In PowerShell:
  - `$env:SMTP_ENABLED='1'; docker compose --profile mail up -d --build`
3. Open MailHog inbox UI:
  - `http://localhost:8025`
4. MailHog SMTP endpoint used by the API:
  - host: `mailhog`
  - port: `1025`

## Stop and reset

1. Stop containers:
  - `docker compose down`
2. Full reset (remove DB volume, then recreate):
  - `docker compose down -v`
  - `docker compose up -d --build`
