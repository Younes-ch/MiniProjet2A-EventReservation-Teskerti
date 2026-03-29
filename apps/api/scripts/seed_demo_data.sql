BEGIN;

TRUNCATE TABLE event_checkins RESTART IDENTITY CASCADE;
TRUNCATE TABLE event_seats RESTART IDENTITY CASCADE;
TRUNCATE TABLE reservations RESTART IDENTITY CASCADE;
TRUNCATE TABLE events RESTART IDENTITY CASCADE;

INSERT INTO events (slug, title, summary, category, location, city, starts_at, price_amount, currency, seats_total, seats_available, visual_tone, created_at, updated_at)
VALUES
    ('midnight-resonance-2-0', 'Midnight Resonance 2.0', 'A live electronic fusion set with immersive lighting and cinematic visuals.', 'Electronic Fusion', 'The Warehouse District', 'Casablanca', '2026-10-12 20:30:00', 45, 'USD', 300, 294, 'indigo', NOW(), NOW()),
    ('ephemeral-visions-gallery', 'Ephemeral Visions Gallery', 'An interactive modern art showcase with guided storytelling sessions.', 'Modern Art', 'Skyline Atrium', 'Rabat', '2026-10-15 17:00:00', 120, 'USD', 120, 0, 'cyan', NOW(), NOW()),
    ('future-loop-ai-2026', 'Future Loop: AI 2026', 'A one-day summit on practical AI systems, workshops, and startup demos.', 'Tech Summit', 'Innovation Hub', 'Tangier', '2026-10-18 09:00:00', 299, 'USD', 450, 447, 'amber', NOW(), NOW()),
    ('atlas-developer-summit', 'Atlas Developer Summit', 'A full-day product engineering event with talks and workshops.', 'Developer Conference', 'Atlas Convention Hall', 'Marrakech', '2026-11-06 09:30:00', 89.5, 'USD', 80, 1, 'indigo', NOW(), NOW()),
    ('design-systems-live', 'Design Systems Live', 'Practical sessions on scalable design systems and UI governance.', 'Design Conference', 'Harbor Expo Center', 'Agadir', '2026-11-14 10:00:00', 65, 'USD', 60, 58, 'cyan', NOW(), NOW()),
    ('solar-sound-sessions', 'Solar Sound Sessions', 'An outdoor sunset concert series with curated electronic sets.', 'Live Music', 'Sunset Arena', 'Essaouira', '2026-11-21 18:00:00', 39, 'USD', 40, 0, 'amber', NOW(), NOW());

INSERT INTO event_seats (event_id, seat_label, zone, seat_status)
SELECT event.id, 'S-' || LPAD(seat_index::text, 4, '0'), 'main', 'available'
FROM events event
CROSS JOIN LATERAL generate_series(1, event.seats_total) AS seat_index;

INSERT INTO reservations (event_id, reservation_id, qr_code_token, attendee_name, attendee_email, attendee_phone, status, seat_labels, created_at, checked_in_at)
VALUES
    ((SELECT id FROM events WHERE slug = 'midnight-resonance-2-0'), 'RSV-DEMO-CF01', 'DEMO-CONFIRMED-TOKEN-01', 'Demo Confirmed One', 'confirmed.one@example.com', '+212 600 000 001', 'confirmed', '["A-01","A-02"]'::json, NOW() - INTERVAL '3 hours', NOW() - INTERVAL '1 hour'),
    ((SELECT id FROM events WHERE slug = 'midnight-resonance-2-0'), 'RSV-DEMO-CF02', 'DEMO-CONFIRMED-TOKEN-02', 'Demo Confirmed Two', 'confirmed.two@example.com', '+212 600 000 002', 'confirmed', '["A-03"]'::json, NOW() - INTERVAL '2 hours', NULL),
    ((SELECT id FROM events WHERE slug = 'midnight-resonance-2-0'), 'RSV-DEMO-CF03', 'DEMO-CONFIRMED-TOKEN-03', 'Demo Confirmed Three', 'confirmed.three@example.com', '+212 600 000 003', 'confirmed', '["A-04","A-05","A-06"]'::json, NOW() - INTERVAL '90 minutes', NULL),
    ((SELECT id FROM events WHERE slug = 'future-loop-ai-2026'), 'RSV-DEMO-CF04', 'DEMO-CONFIRMED-TOKEN-04', 'Demo Checked Manual', 'manual.checkin@example.com', '+212 600 000 004', 'confirmed', '["C-01"]'::json, NOW() - INTERVAL '4 hours', NOW() - INTERVAL '3 hours'),
    ((SELECT id FROM events WHERE slug = 'design-systems-live'), 'RSV-DEMO-CF05', 'DEMO-CONFIRMED-TOKEN-05', 'Demo Design Guest', 'design.guest@example.com', '+212 600 000 005', 'confirmed', '["E-01","E-02"]'::json, NOW() - INTERVAL '50 minutes', NULL),
    ((SELECT id FROM events WHERE slug = 'future-loop-ai-2026'), 'RSV-DEMO-CN01', 'DEMO-CANCELLED-TOKEN-01', 'Demo Cancelled One', 'cancelled.one@example.com', '+212 600 000 006', 'cancelled', '["C-08"]'::json, NOW() - INTERVAL '100 minutes', NULL),
    ((SELECT id FROM events WHERE slug = 'atlas-developer-summit'), 'RSV-DEMO-CN02', 'DEMO-CANCELLED-TOKEN-02', 'Demo Cancelled Two', 'cancelled.two@example.com', '+212 600 000 007', 'cancelled', '["D-10","D-11"]'::json, NOW() - INTERVAL '70 minutes', NULL),
    ((SELECT id FROM events WHERE slug = 'ephemeral-visions-gallery'), 'RSV-DEMO-WL01', 'DEMO-WAITLIST-TOKEN-01', 'Demo Waitlist One', 'waitlist.one@example.com', '+212 600 000 008', 'waitlisted', '[]'::json, NOW() - INTERVAL '45 minutes', NULL),
    ((SELECT id FROM events WHERE slug = 'ephemeral-visions-gallery'), 'RSV-DEMO-WL02', 'DEMO-WAITLIST-TOKEN-02', 'Demo Waitlist Two', 'waitlist.two@example.com', '+212 600 000 009', 'waitlisted', '[]'::json, NOW() - INTERVAL '30 minutes', NULL),
    ((SELECT id FROM events WHERE slug = 'solar-sound-sessions'), 'RSV-DEMO-WL03', 'DEMO-WAITLIST-TOKEN-03', 'Demo Waitlist Three', 'waitlist.three@example.com', '+212 600 000 010', 'waitlisted', '[]'::json, NOW() - INTERVAL '20 minutes', NULL),
    ((SELECT id FROM events WHERE slug = 'atlas-developer-summit'), 'RSV-DEMO-WL04', 'DEMO-WAITLIST-TOKEN-04', 'Demo Waitlist Four', 'waitlist.four@example.com', '+212 600 000 011', 'waitlisted', '[]'::json, NOW() - INTERVAL '10 minutes', NULL);

UPDATE event_seats
SET seat_status = 'booked',
    reserved_by_reservation_id = (SELECT id FROM reservations WHERE reservation_id = 'RSV-DEMO-CF01')
WHERE event_id = (SELECT id FROM events WHERE slug = 'midnight-resonance-2-0')
  AND seat_label IN ('S-0001', 'S-0002');

UPDATE event_seats
SET seat_status = 'booked',
    reserved_by_reservation_id = (SELECT id FROM reservations WHERE reservation_id = 'RSV-DEMO-CF02')
WHERE event_id = (SELECT id FROM events WHERE slug = 'midnight-resonance-2-0')
  AND seat_label = 'S-0003';

UPDATE event_seats
SET seat_status = 'booked',
    reserved_by_reservation_id = (SELECT id FROM reservations WHERE reservation_id = 'RSV-DEMO-CF03')
WHERE event_id = (SELECT id FROM events WHERE slug = 'midnight-resonance-2-0')
  AND seat_label IN ('S-0004', 'S-0005', 'S-0006');

UPDATE event_seats
SET seat_status = 'booked',
    reserved_by_reservation_id = (SELECT id FROM reservations WHERE reservation_id = 'RSV-DEMO-CF04')
WHERE event_id = (SELECT id FROM events WHERE slug = 'future-loop-ai-2026')
  AND seat_label = 'S-0001';

UPDATE event_seats
SET seat_status = 'blocked'
WHERE event_id = (SELECT id FROM events WHERE slug = 'future-loop-ai-2026')
  AND seat_label = 'S-0002';

UPDATE event_seats
SET seat_status = 'held'
WHERE event_id = (SELECT id FROM events WHERE slug = 'future-loop-ai-2026')
  AND seat_label = 'S-0003';

UPDATE event_seats
SET seat_status = 'booked',
    reserved_by_reservation_id = (SELECT id FROM reservations WHERE reservation_id = 'RSV-DEMO-CF05')
WHERE event_id = (SELECT id FROM events WHERE slug = 'design-systems-live')
  AND seat_label IN ('S-0001', 'S-0002');

UPDATE event_seats
SET seat_status = 'booked'
WHERE event_id = (SELECT id FROM events WHERE slug = 'ephemeral-visions-gallery');

UPDATE event_seats
SET seat_status = 'booked'
WHERE event_id = (SELECT id FROM events WHERE slug = 'solar-sound-sessions');

UPDATE event_seats
SET seat_status = 'booked'
WHERE event_id = (SELECT id FROM events WHERE slug = 'atlas-developer-summit');

UPDATE event_seats
SET seat_status = 'available'
WHERE event_id = (SELECT id FROM events WHERE slug = 'atlas-developer-summit')
  AND seat_label = 'S-0080';

INSERT INTO event_checkins (reservation_id, checked_in_by, checked_in_at, method)
SELECT id, 'alex@example.com', NOW() - INTERVAL '1 hour', 'qr_scan'
FROM reservations
WHERE reservation_id = 'RSV-DEMO-CF01';

INSERT INTO event_checkins (reservation_id, checked_in_by, checked_in_at, method)
SELECT id, 'alex@example.com', NOW() - INTERVAL '3 hours', 'manual'
FROM reservations
WHERE reservation_id = 'RSV-DEMO-CF04';

COMMIT;
