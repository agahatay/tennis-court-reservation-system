<?php

declare(strict_types=1);

/**
 * Administrator authentication.
 * Passwords are verified against password_hash() hashes stored in the admins
 * table; nothing secret is stored in the source code.
 */

const ADMIN_SESSION_KEY = 'admin';

/**
 * Hash of a random, discarded password. Verifying against it when a username
 * does not exist keeps the response time the same as for a wrong password, so
 * the login form does not reveal which usernames exist.
 */
const DUMMY_PASSWORD_HASH = '$2y$10$P1FxiRBr8qW6f/nEFXn2Q./r7Ixsy4KnnuNxP3ume6VJEF2/Mf0oS';

function attempt_admin_login(string $username, string $password): bool
{
    $admin = $username === '' ? null : find_admin_by_username($username);
    $passwordMatches = password_verify($password, $admin['password_hash'] ?? DUMMY_PASSWORD_HASH);

    if ($admin === null || !$passwordMatches) {
        return false;
    }

    $adminId = (int) $admin['admin_id'];
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        update_admin_password_hash($adminId, password_hash($password, PASSWORD_DEFAULT));
    }

    // New session id after login prevents session fixation; new CSRF token too.
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $_SESSION[ADMIN_SESSION_KEY] = [
        'id' => $adminId,
        'username' => (string) $admin['username'],
        'last_activity' => time(),
    ];
    record_admin_login($adminId);

    return true;
}

/** @return array{id: int, username: string, last_activity: int}|null */
function current_admin(): ?array
{
    $admin = $_SESSION[ADMIN_SESSION_KEY] ?? null;
    if (!is_array($admin) || !isset($admin['id'], $admin['username'], $admin['last_activity'])) {
        return null;
    }

    if (time() - (int) $admin['last_activity'] > app_config()['session_idle_timeout']) {
        unset($_SESSION[ADMIN_SESSION_KEY]);
        return null;
    }

    return $admin;
}

/**
 * Guards an admin page or action. Unauthenticated visitors are redirected to
 * the login page; authenticated responses are marked as non-cacheable because
 * they contain customer data.
 *
 * @return array{id: int, username: string, last_activity: int}
 */
function require_admin(): array
{
    $admin = current_admin();
    if ($admin === null) {
        flash('info', 'Please sign in to access the admin panel.');
        redirect('admin/login.php');
    }

    $_SESSION[ADMIN_SESSION_KEY]['last_activity'] = time();
    header('Cache-Control: no-store, max-age=0');

    return $admin;
}

function logout_admin(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }

    session_destroy();
}
