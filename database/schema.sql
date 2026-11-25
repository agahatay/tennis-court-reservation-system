-- Tennis Court Reservation and Management System: database schema
-- Target: MariaDB 10.4+ (XAMPP) / MySQL 8.0.16+
--
-- Fresh installs only. Create an empty database first, then import this file:
--   CREATE DATABASE tennis_court_reservation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   mysql -u root -p tennis_court_reservation < database/schema.sql
--
-- To upgrade an existing database created by the original version of the
-- application, use the scripts in database/migrations/ instead.

SET NAMES utf8mb4;

CREATE TABLE courts (
    court_id    INT          NOT NULL AUTO_INCREMENT,
    court_name  VARCHAR(50)  NOT NULL,
    PRIMARY KEY (court_id),
    UNIQUE KEY uq_courts_court_name (court_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    customer_id      INT          NOT NULL AUTO_INCREMENT,
    name             VARCHAR(50)  NOT NULL,
    -- Phone number exactly as the customer entered it (for display).
    phone            VARCHAR(20)  NOT NULL,
    -- E.164 form used for matching and search, e.g. "+905321234567", so that
    -- "0532...", "532..." and "+90532..." are the same number.
    phone_normalized VARCHAR(20)  NOT NULL,
    PRIMARY KEY (customer_id),
    -- Not UNIQUE on purpose: different people (e.g. family members) may share
    -- a phone number. A booking reuses a customer only when both the
    -- normalized phone and the name match.
    KEY idx_customers_phone_normalized_name (phone_normalized, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookable hourly slots, managed from the admin panel (admin/time-slots.php).
CREATE TABLE time_slots (
    time_slot_id INT  NOT NULL AUTO_INCREMENT,
    start_time   TIME NOT NULL,
    end_time     TIME NOT NULL,
    PRIMARY KEY (time_slot_id),
    UNIQUE KEY uq_time_slots_start_time (start_time),
    CONSTRAINT chk_time_slots_end_after_start CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reservations (
    reservation_id   INT           NOT NULL AUTO_INCREMENT,
    customer_id      INT           NOT NULL,
    court_id         INT           NOT NULL,
    time_slot_id     INT           NOT NULL,
    reservation_date DATE          NOT NULL,
    -- Snapshot of the price at booking time, calculated on the server.
    price            DECIMAL(10,2) NOT NULL,
    is_paid          TINYINT(1)    NOT NULL DEFAULT 0,
    PRIMARY KEY (reservation_id),
    -- A court can only be booked once per date and time slot. This constraint is
    -- what actually prevents double bookings, including concurrent requests.
    UNIQUE KEY uq_reservations_court_date_slot (court_id, reservation_date, time_slot_id),
    KEY idx_reservations_date (reservation_date),
    CONSTRAINT fk_reservations_customer  FOREIGN KEY (customer_id)  REFERENCES customers (customer_id)   ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reservations_court     FOREIGN KEY (court_id)     REFERENCES courts (court_id)         ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reservations_time_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots (time_slot_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_reservations_price_non_negative CHECK (price >= 0),
    CONSTRAINT chk_reservations_is_paid_boolean   CHECK (is_paid IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Administrator accounts. Passwords are stored as password_hash() hashes.
-- Create an account with: php scripts/create-admin.php <username>
CREATE TABLE admins (
    admin_id      INT          NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME     NULL,
    PRIMARY KEY (admin_id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed admin sign-in attempts, used to rate-limit the login form.
-- Rows older than one day are deleted automatically.
CREATE TABLE login_attempts (
    login_attempt_id BIGINT      NOT NULL AUTO_INCREMENT,
    ip_address       VARCHAR(45) NOT NULL,
    username_hash    CHAR(64)    NOT NULL,   -- SHA-256 of the lower-cased username
    attempted_at     DATETIME    NOT NULL,
    PRIMARY KEY (login_attempt_id),
    KEY idx_login_attempts_ip_time (ip_address, attempted_at),
    KEY idx_login_attempts_ip_user_time (ip_address, username_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
