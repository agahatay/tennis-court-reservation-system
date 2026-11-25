<?php

declare(strict_types=1);

function count_reservations_on_date(string $date): int
{
    return (int) db_fetch_value('SELECT COUNT(*) FROM reservations WHERE reservation_date = ?', [$date]);
}

function count_all_reservations(): int
{
    return (int) db_fetch_value('SELECT COUNT(*) FROM reservations');
}

/**
 * The court with the most reservations (ties resolved by court order).
 *
 * @return array{court_name: string, reservation_count: int}|null
 */
function find_most_booked_court(): ?array
{
    return db_fetch_one(
        'SELECT c.court_name, COUNT(*) AS reservation_count
         FROM reservations r
         JOIN courts c ON c.court_id = r.court_id
         GROUP BY c.court_id, c.court_name
         ORDER BY reservation_count DESC, c.court_id
         LIMIT 1'
    );
}

/**
 * Income across all reservations, split by payment status.
 *
 * @return array{total_amount: string, paid_amount: string, unpaid_amount: string, paid_count: int, unpaid_count: int}
 */
function get_income_summary(): array
{
    $row = db_fetch_one(
        'SELECT COALESCE(SUM(price), 0)                          AS total_amount,
                COALESCE(SUM(CASE WHEN is_paid = 1 THEN price END), 0) AS paid_amount,
                COALESCE(SUM(CASE WHEN is_paid = 0 THEN price END), 0) AS unpaid_amount,
                COALESCE(SUM(is_paid = 1), 0)                    AS paid_count,
                COALESCE(SUM(is_paid = 0), 0)                    AS unpaid_count
         FROM reservations'
    );

    return [
        'total_amount' => (string) $row['total_amount'],
        'paid_amount' => (string) $row['paid_amount'],
        'unpaid_amount' => (string) $row['unpaid_amount'],
        'paid_count' => (int) $row['paid_count'],
        'unpaid_count' => (int) $row['unpaid_count'],
    ];
}

/**
 * The next reservations that have not finished yet (in progress or later).
 *
 * @return list<array<string, mixed>>
 */
function get_upcoming_reservations(DateTimeImmutable $now, int $limit = 5): array
{
    $today = $now->format('Y-m-d');

    return db_fetch_all(
        RESERVATION_SELECT_SQL . '
         WHERE r.reservation_date > ? OR (r.reservation_date = ? AND ts.end_time > ?)
         ORDER BY r.reservation_date, ts.start_time, c.court_id
         LIMIT ?',
        [$today, $today, $now->format('H:i:s'), $limit]
    );
}
