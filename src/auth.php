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

/*
 * Login rate limiting (sliding window). Within LOGIN_THROTTLE_WINDOW_SECONDS:
 *   - at most LOGIN_MAX_FAILURES_PER_USERNAME failures for one username from one IP
 *   - at most LOGIN_MAX_FAILURES_PER_IP failures from one IP across all usernames
 * Further attempts are refused until the oldest counted failure leaves the
 * window. Limits are per IP on purpose: an account-wide lock would let anyone
 * lock the real administrator out. A successful login clears that username's
 * failures for the IP.
 */
const LOGIN_THROTTLE_WINDOW_SECONDS = 900;
const LOGIN_MAX_FAILURES_PER_USERNAME = 5;
const LOGIN_MAX_FAILURES_PER_IP = 20;

/**
 * The client address used for rate limiting. REMOTE_ADDR is used deliberately:
 * headers such as X-Forwarded-For are set by the client and could be forged
 * to bypass the limit. Behind a reverse proxy this must be adapted.
 */
function client_ip_address(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

/** Seconds until the next sign-in attempt is allowed (0 = allowed now). */
function login_lockout_seconds_remaining(string $ipAddress, string $username, DateTimeImmutable $now): int
{
    $windowStart = $now->modify('-' . LOGIN_THROTTLE_WINDOW_SECONDS . ' seconds');
    $remaining = 0;

    $limits = [
        [$username, LOGIN_MAX_FAILURES_PER_USERNAME],
        [null, LOGIN_MAX_FAILURES_PER_IP],
    ];
    foreach ($limits as [$scopeUsername, $maxFailures]) {
        $oldestCountedFailure = find_nth_recent_failed_login($ipAddress, $scopeUsername, $windowStart, $maxFailures);
        if ($oldestCountedFailure !== null) {
            $unlockAt = $oldestCountedFailure->getTimestamp() + LOGIN_THROTTLE_WINDOW_SECONDS;
            $remaining = max($remaining, $unlockAt - $now->getTimestamp());
        }
    }

    return max(0, $remaining);
}

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
