<?php

declare(strict_types=1);

/** @return list<array{court_id: int, court_name: string}> */
function get_all_courts(): array
{
    return db_fetch_all('SELECT court_id, court_name FROM courts ORDER BY court_id');
}

/** @return array{court_id: int, court_name: string}|null */
function find_court(int $courtId): ?array
{
    return db_fetch_one('SELECT court_id, court_name FROM courts WHERE court_id = ?', [$courtId]);
}
