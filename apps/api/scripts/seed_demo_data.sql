BEGIN;

TRUNCATE TABLE event_checkins RESTART IDENTITY CASCADE;
TRUNCATE TABLE event_seats RESTART IDENTITY CASCADE;
TRUNCATE TABLE reservations RESTART IDENTITY CASCADE;
TRUNCATE TABLE events RESTART IDENTITY CASCADE;

INSERT INTO events (slug, title, summary, category, location, city, starts_at, price_amount, currency, seats_total, seats_available, visual_tone, created_at, updated_at)
VALUES
    ('midnight-resonance-2-0', 'Midnight Resonance 2.0', 'A live electronic fusion set with immersive lighting and cinematic visuals.', 'Electronic Fusion', 'The Warehouse District', 'Casablanca', '2026-10-12 20:30:00', 45, 'USD', 300, 298, 'indigo', NOW(), NOW()),
    ('ephemeral-visions-gallery', 'Ephemeral Visions Gallery', 'An interactive modern art showcase with guided storytelling sessions.', 'Modern Art', 'Skyline Atrium', 'Rabat', '2026-10-15 17:00:00', 120, 'USD', 120, 0, 'cyan', NOW(), NOW()),
    ('future-loop-ai-2026', 'Future Loop: AI 2026', 'A one-day summit on practical AI systems, workshops, and startup demos.', 'Tech Summit', 'Innovation Hub', 'Tangier', '2026-10-18 09:00:00', 299, 'USD', 450, 447, 'amber', NOW(), NOW());

INSERT INTO event_seats (event_id, seat_label, zone, seat_status)
SELECT event.id, 'S-' || LPAD(seat_index::text, 4, '0'), 'main', 'available'
FROM events event
CROSS JOIN LATERAL generate_series(1, event.seats_total) AS seat_index;

INSERT INTO reservations (event_id, reservation_id, qr_code_token, attendee_name, attendee_email, attendee_phone, status, seat_labels, created_at, checked_in_at)
VALUES
    ((SELECT id FROM events WHERE slug = 'midnight-resonance-2-0'), 'RSV-DEMO-CF01', 'DEMO-CONFIRMED-TOKEN-01', 'Demo Confirmed', 'confirmed@example.com', '+212 600 000 001', 'confirmed', '["A-01","A-02"]'::json, NOW() - INTERVAL '3 hours', NOW() - INTERVAL '1 hour'),
    ((SELECT id FROM events WHERE slug = 'future-loop-ai-2026'), 'RSV-DEMO-CN01', 'DEMO-CANCELLED-TOKEN-01', 'Demo Cancelled', 'cancelled@example.com', '+212 600 000 002', 'cancelled', '["B-05"]'::json, NOW() - INTERVAL '2 hours', NULL),
    ((SELECT id FROM events WHERE slug = 'ephemeral-visions-gallery'), 'RSV-DEMO-WL01', 'DEMO-WAITLIST-TOKEN-01', 'Demo Waitlist', 'waitlist@example.com', '+212 600 000 003', 'waitlisted', '[]'::json, NOW() - INTERVAL '30 minutes', NULL);

UPDATE event_seats
SET seat_status = 'booked',
    reserved_by_reservation_id = (SELECT id FROM reservations WHERE reservation_id = 'RSV-DEMO-CF01')
WHERE event_id = (SELECT id FROM events WHERE slug = 'midnight-resonance-2-0')
  AND seat_label IN ('S-0001', 'S-0002');

INSERT INTO event_checkins (reservation_id, checked_in_by, checked_in_at, method)
SELECT id, 'alex@example.com', NOW() - INTERVAL '1 hour', 'qr_scan'
FROM reservations
WHERE reservation_id = 'RSV-DEMO-CF01';

COMMIT;
