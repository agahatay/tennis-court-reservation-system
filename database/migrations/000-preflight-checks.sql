-- =============================================================================
-- 000 - Pre-flight checks for upgrading a legacy "tennisdb" database
-- =============================================================================
-- READ-ONLY. This script only runs SELECT statements and changes nothing.
--
-- Run it against the legacy database BEFORE 001-upgrade-legacy-schema.sql:
--   mysql -u root -p tennisdb < database/migrations/000-preflight-checks.sql
--
-- Every check labelled [BLOCKER] must return zero rows, otherwise the upgrade
-- will fail part-way. Fix or remove the reported rows manually first.
-- Checks labelled [INFO] do not block the upgrade; they are for review.
-- =============================================================================

SELECT '[INFO] Columns already present (if reservation_date / time_slot_id are listed, the upgrade was already applied - do NOT run 001 again)' AS check_name;
SELECT table_name, column_name, column_type, is_nullable
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name IN ('reservations', 'time_slots')
ORDER BY table_name, ordinal_position;

SELECT '[BLOCKER] Reservations with missing required values' AS check_name;
SELECT reservation_id, customer_id, court_id, `date`, `time`
FROM reservations
WHERE customer_id IS NULL OR court_id IS NULL OR `date` IS NULL OR `time` IS NULL;

SELECT '[BLOCKER] Reservations pointing to a customer that does not exist' AS check_name;
SELECT r.reservation_id, r.customer_id
FROM reservations r
LEFT JOIN customers c ON c.customer_id = r.customer_id
WHERE r.customer_id IS NOT NULL AND c.customer_id IS NULL;

SELECT '[BLOCKER] Reservations pointing to a court that does not exist' AS check_name;
SELECT r.reservation_id, r.court_id
FROM reservations r
LEFT JOIN courts c ON c.court_id = r.court_id
WHERE r.court_id IS NOT NULL AND c.court_id IS NULL;

SELECT '[BLOCKER] Reservation times that do not match any time slot label' AS check_name;
SELECT r.reservation_id, r.`time`
FROM reservations r
LEFT JOIN time_slots ts ON ts.slot_label = r.`time`
WHERE r.`time` IS NOT NULL AND ts.slot_id IS NULL;

SELECT '[BLOCKER] Double bookings (same court, date and time)' AS check_name;
SELECT court_id, `date`, `time`, COUNT(*) AS bookings, GROUP_CONCAT(reservation_id) AS reservation_ids
FROM reservations
GROUP BY court_id, `date`, `time`
HAVING COUNT(*) > 1;

SELECT '[BLOCKER] Time slot labels that are not in HH:MM-HH:MM format, or end before they start' AS check_name;
SELECT slot_id, slot_label
FROM time_slots
WHERE slot_label IS NULL
   OR slot_label NOT REGEXP '^[0-2][0-9]:[0-5][0-9]-[0-2][0-9]:[0-5][0-9]$'
   OR SUBSTRING_INDEX(slot_label, '-', -1) <= SUBSTRING_INDEX(slot_label, '-', 1);

SELECT '[BLOCKER] Time slots that share the same start time' AS check_name;
SELECT SUBSTRING_INDEX(slot_label, '-', 1) AS start_time, GROUP_CONCAT(slot_id) AS slot_ids
FROM time_slots
GROUP BY SUBSTRING_INDEX(slot_label, '-', 1)
HAVING COUNT(*) > 1;

SELECT '[BLOCKER] Customers or courts with missing names / phones' AS check_name;
SELECT 'customer' AS record_type, customer_id AS id FROM customers WHERE name IS NULL OR name = '' OR phone IS NULL OR phone = ''
UNION ALL
SELECT 'court', court_id FROM courts WHERE court_name IS NULL OR court_name = '';

SELECT '[BLOCKER] Duplicate court names' AS check_name;
SELECT court_name, GROUP_CONCAT(court_id) AS court_ids
FROM courts
GROUP BY court_name
HAVING COUNT(*) > 1;

SELECT '[INFO] Customers sharing a phone number (review before deciding on a UNIQUE phone rule)' AS check_name;
SELECT phone, COUNT(*) AS customers, GROUP_CONCAT(customer_id ORDER BY customer_id) AS customer_ids
FROM customers
GROUP BY phone
HAVING COUNT(*) > 1;

SELECT '[INFO] Phone numbers that do not fit any Turkish / international format (kept as entered; will not match new bookings)' AS check_name;
SELECT customer_id, phone
FROM customers
WHERE NOT (
       TRIM(phone) LIKE '+%'
    OR REGEXP_REPLACE(phone, '[^0-9]', '') LIKE '00%'
    OR (CHAR_LENGTH(REGEXP_REPLACE(phone, '[^0-9]', '')) = 11 AND REGEXP_REPLACE(phone, '[^0-9]', '') LIKE '0%')
    OR (CHAR_LENGTH(REGEXP_REPLACE(phone, '[^0-9]', '')) = 10 AND REGEXP_REPLACE(phone, '[^0-9]', '') NOT LIKE '0%')
    OR (CHAR_LENGTH(REGEXP_REPLACE(phone, '[^0-9]', '')) = 12 AND REGEXP_REPLACE(phone, '[^0-9]', '') LIKE '90%')
);

SELECT '[INFO] Customers whose phone numbers differ only by formatting (e.g. leading 0, spaces)' AS check_name;
SELECT RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10) AS last_10_digits,
       COUNT(*) AS customers,
       GROUP_CONCAT(DISTINCT phone) AS stored_variants
FROM customers
GROUP BY last_10_digits
HAVING COUNT(DISTINCT phone) > 1;
