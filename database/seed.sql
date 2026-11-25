-- Sample data for local development and demos.
-- All customer names and phone numbers below are FICTIONAL.
-- Reservation dates are relative to the import date so the dashboard always
-- has recent and upcoming data to show.
--
-- Import after schema.sql:
--   mysql -u root -p tennis_court_reservation < database/seed.sql
--
-- No administrator is seeded. Create one with: php scripts/create-admin.php <username>

SET NAMES utf8mb4;

INSERT INTO courts (court_id, court_name) VALUES
    (1, 'Court 1'), (2, 'Court 2'), (3, 'Court 3'), (4, 'Court 4'),
    (5, 'Court 5'), (6, 'Court 6'), (7, 'Court 7');

INSERT INTO time_slots (time_slot_id, start_time, end_time) VALUES
    (1,  '08:00', '09:00'), (2,  '09:00', '10:00'), (3,  '10:00', '11:00'),
    (4,  '11:00', '12:00'), (5,  '12:00', '13:00'), (6,  '13:00', '14:00'),
    (7,  '14:00', '15:00'), (8,  '15:00', '16:00'), (9,  '16:00', '17:00'),
    (10, '17:00', '18:00'), (11, '18:00', '19:00'), (12, '19:00', '20:00'),
    (13, '20:00', '21:00');

INSERT INTO customers (customer_id, name, phone) VALUES
    (1, 'Demo Customer One',   '0500 000 00 01'),
    (2, 'Demo Customer Two',   '0500 000 00 02'),
    (3, 'Demo Customer Three', '0500 000 00 03'),
    (4, 'Demo Customer Four',  '0500 000 00 04'),
    (5, 'Demo Customer Five',  '0500 000 00 05');

INSERT INTO reservations (customer_id, court_id, time_slot_id, reservation_date, price, is_paid) VALUES
    -- past
    (1, 1, 3,  CURDATE() - INTERVAL 2 DAY, 3000.00, 1),
    (2, 2, 5,  CURDATE() - INTERVAL 2 DAY, 3000.00, 1),
    (3, 1, 10, CURDATE() - INTERVAL 1 DAY, 3000.00, 0),
    (4, 3, 11, CURDATE() - INTERVAL 1 DAY, 3000.00, 1),
    -- today
    (5, 1, 13, CURDATE(),                   3000.00, 0),
    (1, 2, 12, CURDATE(),                   3000.00, 1),
    (2, 4, 13, CURDATE(),                   3000.00, 0),
    -- upcoming
    (3, 1, 2,  CURDATE() + INTERVAL 1 DAY, 3000.00, 0),
    (4, 1, 3,  CURDATE() + INTERVAL 1 DAY, 3000.00, 1),
    (5, 5, 9,  CURDATE() + INTERVAL 2 DAY, 3000.00, 0),
    (1, 6, 10, CURDATE() + INTERVAL 3 DAY, 3000.00, 0),
    (2, 1, 11, CURDATE() + INTERVAL 5 DAY, 3000.00, 0);
