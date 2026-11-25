<?php

declare(strict_types=1);

/** @return array{admin_id: int, username: string, password_hash: string}|null */
function find_admin_by_username(string $username): ?array
{
    return db_fetch_one(
        'SELECT admin_id, username, password_hash FROM admins WHERE username = ? LIMIT 1',
        [$username]
    );
}

function count_admins(): int
{
    return (int) db_fetch_value('SELECT COUNT(*) FROM admins');
}

function create_admin(string $username, string $passwordHash): int
{
    return db_insert('INSERT INTO admins (username, password_hash) VALUES (?, ?)', [$username, $passwordHash]);
}

function update_admin_password_hash(int $adminId, string $passwordHash): void
{
    db_execute('UPDATE admins SET password_hash = ? WHERE admin_id = ?', [$passwordHash, $adminId]);
}

function record_admin_login(int $adminId): void
{
    db_execute('UPDATE admins SET last_login_at = NOW() WHERE admin_id = ?', [$adminId]);
}
