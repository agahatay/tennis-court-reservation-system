<?php

declare(strict_types=1);

/**
 * Storage for failed admin sign-in attempts (used by the login rate limiter).
 * Usernames are stored only as SHA-256 hashes, so text typed into the
 * username field (sometimes a password by mistake) is never kept in clear.
 */

function hash_login_username(string $username): string
{
    return hash('sha256', mb_strtolower(trim($username)));
}

function record_failed_login(string $ipAddress, string $username, DateTimeImmutable $at): void
{
    db_execute(
        'INSERT INTO login_attempts (ip_address, username_hash, attempted_at) VALUES (?, ?, ?)',
        [$ipAddress, hash_login_username($username), $at->format('Y-m-d H:i:s')]
    );
}

/**
 * Time of the N-th most recent failure since $since (N = $position, 1-based),
 * optionally limited to one username. Null if there are fewer failures.
 */
function find_nth_recent_failed_login(string $ipAddress, ?string $username, DateTimeImmutable $since, int $position): ?DateTimeImmutable
{
    $sql = 'SELECT attempted_at FROM login_attempts WHERE ip_address = ? AND attempted_at > ?';
    $params = [$ipAddress, $since->format('Y-m-d H:i:s')];

    if ($username !== null) {
        $sql .= ' AND username_hash = ?';
        $params[] = hash_login_username($username);
    }

    $sql .= ' ORDER BY attempted_at DESC LIMIT 1 OFFSET ?';
    $params[] = $position - 1;

    $value = db_fetch_value($sql, $params);

    return $value === null ? null : new DateTimeImmutable((string) $value);
}

function clear_failed_logins(string $ipAddress, string $username): void
{
    db_execute(
        'DELETE FROM login_attempts WHERE ip_address = ? AND username_hash = ?',
        [$ipAddress, hash_login_username($username)]
    );
}

function delete_login_attempts_before(DateTimeImmutable $before): void
{
    db_execute('DELETE FROM login_attempts WHERE attempted_at < ?', [$before->format('Y-m-d H:i:s')]);
}
