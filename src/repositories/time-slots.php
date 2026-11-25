<?php

declare(strict_types=1);

/** @return list<array{time_slot_id: int, start_time: string, end_time: string}> */
function get_all_time_slots(): array
{
    return db_fetch_all('SELECT time_slot_id, start_time, end_time FROM time_slots ORDER BY start_time');
}

/**
 * Time slots with the number of reservations that use each one.
 *
 * @return list<array{time_slot_id: int, start_time: string, end_time: string, reservation_count: int}>
 */
function get_time_slots_with_usage(): array
{
    return db_fetch_all(
        'SELECT ts.time_slot_id, ts.start_time, ts.end_time, COUNT(r.reservation_id) AS reservation_count
         FROM time_slots ts
         LEFT JOIN reservations r ON r.time_slot_id = ts.time_slot_id
         GROUP BY ts.time_slot_id, ts.start_time, ts.end_time
         ORDER BY ts.start_time'
    );
}

/** @return array{time_slot_id: int, start_time: string, end_time: string}|null */
function find_time_slot(int $timeSlotId): ?array
{
    return db_fetch_one(
        'SELECT time_slot_id, start_time, end_time FROM time_slots WHERE time_slot_id = ?',
        [$timeSlotId]
    );
}

/** True if [start, end) overlaps an existing slot. Times are "HH:MM:SS". */
function time_slot_overlaps_existing(string $startTime, string $endTime): bool
{
    return db_fetch_value(
        'SELECT 1 FROM time_slots WHERE start_time < ? AND end_time > ? LIMIT 1',
        [$endTime, $startTime]
    ) !== null;
}

function create_time_slot(string $startTime, string $endTime): int
{
    return db_insert('INSERT INTO time_slots (start_time, end_time) VALUES (?, ?)', [$startTime, $endTime]);
}

function count_reservations_for_time_slot(int $timeSlotId): int
{
    return (int) db_fetch_value('SELECT COUNT(*) FROM reservations WHERE time_slot_id = ?', [$timeSlotId]);
}

/** Deletes a slot. The foreign key prevents deleting slots that have reservations. */
function delete_time_slot(int $timeSlotId): bool
{
    return db_execute('DELETE FROM time_slots WHERE time_slot_id = ?', [$timeSlotId]) > 0;
}
