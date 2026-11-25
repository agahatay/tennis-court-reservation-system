<?php

declare(strict_types=1);

const PAYMENT_FILTERS = ['all', 'paid', 'unpaid'];

/** Columns and joins shared by every reservation listing. */
const RESERVATION_SELECT_SQL = '
    SELECT r.reservation_id, r.reservation_date, r.price, r.is_paid,
           r.court_id, c.court_name,
           r.time_slot_id, ts.start_time, ts.end_time,
           cu.customer_id, cu.name AS customer_name, cu.phone AS customer_phone
    FROM reservations r
    JOIN courts c      ON c.court_id = r.court_id
    JOIN time_slots ts ON ts.time_slot_id = r.time_slot_id
    JOIN customers cu  ON cu.customer_id = r.customer_id
';

/** @return list<int> ids of the time slots already booked for a court on a date */
function get_booked_time_slot_ids(int $courtId, string $reservationDate): array
{
    $rows = db_fetch_all(
        'SELECT time_slot_id FROM reservations WHERE court_id = ? AND reservation_date = ?',
        [$courtId, $reservationDate]
    );

    return array_map(static fn (array $row): int => (int) $row['time_slot_id'], $rows);
}

/**
 * Inserts a reservation. Throws mysqli_sql_exception with code
 * DB_ERROR_DUPLICATE_ENTRY if the court is already booked for that slot.
 */
function insert_reservation(int $customerId, int $courtId, int $timeSlotId, string $reservationDate, string $price): int
{
    return db_insert(
        'INSERT INTO reservations (customer_id, court_id, time_slot_id, reservation_date, price, is_paid)
         VALUES (?, ?, ?, ?, ?, 0)',
        [$customerId, $courtId, $timeSlotId, $reservationDate, $price]
    );
}

/** @return array<string, mixed>|null */
function find_reservation(int $reservationId): ?array
{
    return db_fetch_one(RESERVATION_SELECT_SQL . ' WHERE r.reservation_id = ?', [$reservationId]);
}

/**
 * Lists reservations matching the given filters.
 *
 * @param array{search?: string, date?: string, payment?: string} $filters
 *        search:  part of the customer's name or phone number
 *        date:    exact reservation date (YYYY-MM-DD)
 *        payment: "all", "paid" or "unpaid"
 * @param 'asc'|'desc' $direction chronological order
 * @return list<array<string, mixed>>
 */
function search_reservations(array $filters, string $direction = 'asc'): array
{
    $conditions = [];
    $params = [];

    $search = $filters['search'] ?? '';
    if ($search !== '') {
        // Escape LIKE wildcards so "%" or "_" in the search box are matched literally.
        $pattern = '%' . addcslashes($search, '%_\\') . '%';
        $conditions[] = '(cu.name LIKE ? OR cu.phone LIKE ?)';
        array_push($params, $pattern, $pattern);
    }

    $date = $filters['date'] ?? '';
    if ($date !== '') {
        $conditions[] = 'r.reservation_date = ?';
        $params[] = $date;
    }

    $payment = $filters['payment'] ?? 'all';
    if ($payment === 'paid' || $payment === 'unpaid') {
        $conditions[] = 'r.is_paid = ?';
        $params[] = $payment === 'paid' ? 1 : 0;
    }

    $order = $direction === 'desc' ? 'DESC' : 'ASC';
    $sql = RESERVATION_SELECT_SQL
        . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions))
        . " ORDER BY r.reservation_date $order, ts.start_time $order, c.court_id";

    return db_fetch_all($sql, $params);
}

/** @return list<array<string, mixed>> all reservations on a date (for the daily schedule) */
function get_reservations_on_date(string $reservationDate): array
{
    return db_fetch_all(RESERVATION_SELECT_SQL . ' WHERE r.reservation_date = ?', [$reservationDate]);
}

function set_reservation_payment_status(int $reservationId, bool $isPaid): void
{
    db_execute('UPDATE reservations SET is_paid = ? WHERE reservation_id = ?', [$isPaid, $reservationId]);
}

function delete_reservation(int $reservationId): bool
{
    return db_execute('DELETE FROM reservations WHERE reservation_id = ?', [$reservationId]) > 0;
}
