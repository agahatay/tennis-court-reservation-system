-- =============================================================================
-- 001 - Upgrade a legacy "tennisdb" database to the current schema
-- =============================================================================
-- Target: MariaDB 10.4+ (the XAMPP version the original project used).
--
-- NON-DESTRUCTIVE: no table, row or column is deleted. Legacy columns that are
-- replaced (reservations.time, time_slots.slot_label, time_slots.sort_order)
-- are kept, made nullable, and left untouched so the conversion can be checked.
-- They are only removed by 002-drop-legacy-columns.sql, which is optional and
-- must be run separately after verification.
--
-- What it does:
--   * time_slots:    slot_id -> time_slot_id; adds start_time / end_time (TIME)
--                    parsed from the "HH:MM-HH:MM" labels; one slot per start time
--   * reservations:  date -> reservation_date; adds time_slot_id (linked to
--                    time_slots); ensures price DECIMAL(10,2) and is_paid exist;
--                    NOT NULL columns; UNIQUE (court, date, slot); foreign keys
--   * courts/customers: NOT NULL names/phones; UNIQUE court names; phone index
--   * admins:        new table for hashed administrator passwords
--   * customers.phone is deliberately NOT made unique (pending business review)
--
-- BEFORE RUNNING:
--   1. Back up the database:
--        mysqldump -u root -p --routines tennisdb > tennisdb-backup.sql
--   2. Run 000-preflight-checks.sql and resolve every [BLOCKER] result.
--   3. Review @legacy_reservation_price below.
--
-- MySQL/MariaDB commit each ALTER TABLE immediately, so this script cannot be
-- rolled back automatically. If it stops with an error, restore the backup,
-- fix the reported problem, and run it again from the start.
--
-- Run once:
--   mysql -u root -p tennisdb < database/migrations/001-upgrade-legacy-schema.sql
-- =============================================================================

SET NAMES utf8mb4;
-- Fail loudly instead of silently truncating or converting bad values.
SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- Price applied to legacy reservations that have no stored price.
-- The original application charged a fixed 3000 per one-hour slot.
SET @legacy_reservation_price = 3000.00;

-- -----------------------------------------------------------------------------
-- 1. courts
-- -----------------------------------------------------------------------------
ALTER TABLE courts
    MODIFY court_name VARCHAR(50) NOT NULL,
    ADD UNIQUE KEY uq_courts_court_name (court_name);

-- -----------------------------------------------------------------------------
-- 2. customers
-- -----------------------------------------------------------------------------
ALTER TABLE customers
    MODIFY name  VARCHAR(50) NOT NULL,
    MODIFY phone VARCHAR(20) NOT NULL,
    ADD KEY idx_customers_phone (phone);

-- -----------------------------------------------------------------------------
-- 3. time_slots: structured start/end times
-- -----------------------------------------------------------------------------
ALTER TABLE time_slots
    CHANGE COLUMN slot_id time_slot_id INT NOT NULL AUTO_INCREMENT,
    ADD COLUMN start_time TIME NULL AFTER time_slot_id,
    ADD COLUMN end_time   TIME NULL AFTER start_time,
    MODIFY slot_label VARCHAR(20) NULL COMMENT 'Legacy label, replaced by start_time/end_time. Removed by migration 002.',
    MODIFY sort_order INT NULL         COMMENT 'Legacy ordering, slots are now ordered by start_time. Removed by migration 002.';

UPDATE time_slots
SET start_time = STR_TO_DATE(TRIM(SUBSTRING_INDEX(slot_label, '-', 1)),  '%H:%i'),
    end_time   = STR_TO_DATE(TRIM(SUBSTRING_INDEX(slot_label, '-', -1)), '%H:%i');

ALTER TABLE time_slots
    MODIFY start_time TIME NOT NULL,
    MODIFY end_time   TIME NOT NULL,
    ADD UNIQUE KEY uq_time_slots_start_time (start_time),
    ADD CONSTRAINT chk_time_slots_end_after_start CHECK (end_time > start_time);

-- -----------------------------------------------------------------------------
-- 4. reservations: price / payment columns (may or may not exist already)
-- -----------------------------------------------------------------------------
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS price   DECIMAL(10,2) NULL,
    ADD COLUMN IF NOT EXISTS is_paid TINYINT(1)    NULL DEFAULT 0;

UPDATE reservations SET price = @legacy_reservation_price WHERE price IS NULL;
-- The original code treated only is_paid = 1 as paid; keep exactly that meaning.
UPDATE reservations SET is_paid = IF(is_paid = 1, 1, 0);

-- -----------------------------------------------------------------------------
-- 5. reservations: rename date, link to time_slots
-- -----------------------------------------------------------------------------
ALTER TABLE reservations
    CHANGE COLUMN `date` reservation_date DATE NOT NULL,
    ADD COLUMN time_slot_id INT NULL AFTER court_id,
    MODIFY customer_id INT NOT NULL,
    MODIFY court_id    INT NOT NULL,
    MODIFY price       DECIMAL(10,2) NOT NULL,
    MODIFY is_paid     TINYINT(1)    NOT NULL DEFAULT 0;

UPDATE reservations r
JOIN time_slots ts ON ts.slot_label = r.`time`
SET r.time_slot_id = ts.time_slot_id;

ALTER TABLE reservations
    MODIFY time_slot_id INT NOT NULL,
    MODIFY `time` VARCHAR(20) NULL COMMENT 'Legacy label, replaced by time_slot_id. Removed by migration 002.',
    ADD UNIQUE KEY uq_reservations_court_date_slot (court_id, reservation_date, time_slot_id),
    ADD KEY idx_reservations_date (reservation_date),
    ADD CONSTRAINT fk_reservations_customer  FOREIGN KEY (customer_id)  REFERENCES customers (customer_id)   ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT fk_reservations_court     FOREIGN KEY (court_id)     REFERENCES courts (court_id)         ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT fk_reservations_time_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots (time_slot_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT chk_reservations_price_non_negative CHECK (price >= 0),
    ADD CONSTRAINT chk_reservations_is_paid_boolean   CHECK (is_paid IN (0, 1));

-- -----------------------------------------------------------------------------
-- 6. admins
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    admin_id      INT          NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME     NULL,
    PRIMARY KEY (admin_id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Verification output (every reservation must show its converted slot)
-- -----------------------------------------------------------------------------
SELECT r.reservation_id, r.reservation_date, r.`time` AS legacy_time,
       ts.start_time, ts.end_time, r.price, r.is_paid
FROM reservations r
JOIN time_slots ts ON ts.time_slot_id = r.time_slot_id
ORDER BY r.reservation_id;
