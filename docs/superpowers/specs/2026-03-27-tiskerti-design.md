# Tiskerti Design Specification

## 1. Objectives and Constraints

### 1.1 Primary Objective

Build a full-stack event reservation platform where:

- Users can browse events and reserve seats.
- Admins can manage events and reservations through secured interfaces.
- Authentication combines password-based login (baseline) and passkeys/WebAuthn (advanced).

### 1.2 Required Technologies

- Frontend: React with Vite
- Backend: Symfony API
- Database: PostgreSQL
- Auth: JWT + Refresh token + Passkeys/WebAuthn
- Infra: Docker for local development

### 1.3 Assignment and Team Constraints

- Work incrementally, step by step (no full scaffold in one shot).
- Frequent, meaningful commits.
- Branch model: main, dev, feature/*.
- Local-first implementation; production hardening is out of scope.

## 2. Scope Definition

### 2.1 Baseline Features (Assignment Core)

#### User Side

- Password login
- Event list from database
- Event detail page (description, date/time, location, image)
- Reservation form (name, email, phone)
- Save reservations to database
- Confirmation message after booking

#### Admin Side

- Password login
- Dashboard listing events
- Full CRUD for events
- View reservations by event
- Secure logout

#### Security Baseline

- JWT access token for protected API calls
- Refresh token endpoint/flow

### 2.2 Aggressive Showcase Scope

#### Guaranteed Showcase Features

1. Interactive map on event detail
2. QR ticket generation and QR check-in validation
3. Seat selection map
4. Admin analytics dashboard
5. Branded PDF ticket export

#### Stretch Features

1. Email confirmation workflow (local SMTP/Mailhog)
2. Calendar export (ICS)
3. Waitlist for sold-out events

## 3. Chosen Architecture

### 3.1 Approach

Modular monolith:

- One React app with role-based routing
- One Symfony API split into modules
- One PostgreSQL database
- One Docker Compose setup for all services

This approach minimizes integration risk while supporting an aggressive feature scope.

### 3.2 Frontend Architecture (React + Vite)

- Single app, role-based route guards
- Public routes: discover events, detail view, reservation, tickets
- Admin routes: event CRUD, reservation supervision, analytics, check-in
- Auth state manager for JWT + refresh + passkey state
- Domain-based UI modules:
  - auth
  - events
  - reservations
  - tickets
  - admin

### 3.3 Backend Architecture (Symfony)

Symfony API modules:

- Auth module
  - Password auth
  - JWT issuance and refresh
  - Passkey register/login endpoints
- Events module
  - Public read
  - Admin CRUD
- Reservations module
  - Booking logic
  - Seat locking and validation
- Tickets module
  - QR generation
  - PDF rendering
  - Check-in validation
- Analytics module
  - Aggregate metrics for admin dashboard
- Integrations module
  - Map/geolocation helpers
  - Email adapter
  - ICS generation

### 3.4 Infrastructure (Docker Local)

Target local services:

- frontend (Vite)
- api (Symfony + PHP)
- db (PostgreSQL)
- proxy (Nginx)
- optional: mailhog and pgadmin through compose profiles

## 4. Data Model (Core + Practical Extensions)

### 4.1 users

- id (uuid)
- email (unique)
- password_hash
- roles (ROLE_USER / ROLE_ADMIN)
- display_name
- is_active
- last_login_at
- created_at
- updated_at

### 4.2 events

- id (uuid)
- title
- description
- date (kept for assignment compatibility)
- location
- seats
- image
- slug
- category
- price
- currency
- status (draft/published/cancelled)
- lat
- lng
- starts_at
- ends_at
- created_by
- created_at
- updated_at

### 4.3 reservations

- id (uuid)
- event_id (fk)
- user_id (nullable fk)
- name
- email
- phone
- quantity
- seat_labels (json)
- status (confirmed/cancelled/waitlisted)
- qr_code_token (unique)
- ticket_pdf_path
- checked_in_at
- created_at
- updated_at

### 4.4 event_seats

- id (uuid)
- event_id (fk)
- seat_label
- zone
- seat_status (available/held/booked/blocked)
- reserved_by_reservation_id (nullable fk)
- unique(event_id, seat_label)

### 4.5 webauthn_credentials

- id (uuid)
- user_id (fk)
- credential_id (unique)
- credential_public_key
- sign_count
- transports
- name
- last_used_at
- created_at
- updated_at

### 4.6 refresh_tokens

- managed using Symfony refresh token bundle table

### 4.7 event_checkins (audit)

- id (uuid)
- reservation_id (fk)
- checked_in_by (fk user)
- checked_in_at
- method (qr_scan/manual)

### 4.8 Integrity Rules

- No overbooking (transactional reservation flow)
- Unique QR token per reservation
- Seat status updates are atomic
- Only published events appear in public APIs

## 5. Authentication and Security Design (Local-first)

### 5.1 Auth Modes

1. Password login (phase 1 baseline)
2. Passkey enrollment/login (phase 2)
3. Admin policy: passkey enrollment required after first password login

### 5.2 JWT Strategy

- Access token: short TTL (15 to 30 min)
- Refresh token rotation enabled
- JWT Bearer for protected routes
- Role claims for route and endpoint authorization

### 5.3 WebAuthn Flow

- Register options -> browser create credential -> verify -> save credential
- Login options -> browser get assertion -> verify -> issue JWT + refresh

### 5.4 Local Security Baseline

- Argon2id password hashing
- Endpoint rate limiting for auth routes
- Restricted CORS to local frontend origin
- Challenge expiry handling
- Basic security/audit logs for auth and check-in actions

### 5.5 Token Storage Decision

For project simplicity and local execution, use localStorage for access and refresh tokens in initial phases. Document the tradeoff in README and provide upgrade notes for HttpOnly cookies as a future improvement.

## 6. Phase Delivery Plan

### Phase 0 - Repository and Foundation

- Initialize repository
- Create project structure and docs
- Add local Docker Compose baseline
- Add initial README skeleton

### Phase 1 - Core Platform and JWT

- Password auth + JWT + refresh
- Public event browsing and detail pages
- Reservation flow and confirmation
- Admin event CRUD and reservation view

### Phase 2 - Passkeys Integration

- Register/login passkey endpoints
- React passkey integration
- Admin passkey-required policy

### Phase 3 - Guaranteed Showcase Features

- Interactive event map
- Seat selection map
- QR ticket generation and check-in
- PDF ticket export
- Admin analytics dashboard

### Phase 4 - Stretch Features

- Email confirmations (Mailhog)
- ICS export
- Waitlist flow

## 7. Testing Strategy

### 7.1 Backend

- API tests for auth routes (password + JWT + passkeys)
- Reservation tests (booking integrity, seat availability)
- Ticket/check-in tests

### 7.2 Frontend

- Critical flow tests:
  - Login
  - Reserve
  - Admin event CRUD path

### 7.3 Demo Readiness

- Manual demonstration checklist for grading
- Seed data scripts for realistic scenarios

## 8. Git and Collaboration Strategy

### 8.1 Branch Model

- main: stable demo-ready code
- dev: integration
- feature/*: isolated feature development

### 8.2 Commit Policy

- Frequent small commits grouped by coherent task slices
- No generated co-author trailers in commit messages
- Start timeline from early week dates for initial commit history

### 8.3 Milestone Structure

- Milestone A: Foundation + Docker baseline
- Milestone B: Core auth + reservations
- Milestone C: Passkeys
- Milestone D: Guaranteed showcase features
- Milestone E: Stretch features and polish

## 9. Non-goals

Out of scope for this university local project:

- Multi-region deployment
- High-scale production optimization
- Full SOC/enterprise compliance controls
- Complex microservices decomposition

## 10. Risks and Mitigations

### Risk 1: Aggressive scope overload

Mitigation: guaranteed vs stretch split; gate by phase exit criteria.

### Risk 2: Passkey integration delays

Mitigation: keep password+JWT baseline independently shippable first.

### Risk 3: Seat map + overbooking complexity

Mitigation: transactional server-side booking with pessimistic checks.

### Risk 4: Demo instability

Mitigation: deterministic seed data, scripted demo paths, local-only fixed environment via Docker.

## 11. Expected Deliverables

- Working local Docker environment
- React frontend with public and admin interfaces
- Symfony API with JWT and passkeys
- PostgreSQL schema and migrations
- Assignment baseline features complete
- Guaranteed showcase features complete
- README with setup, run, and demo instructions
