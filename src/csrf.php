<?php

declare(strict_types=1);

/**
 * CSRF protection (synchronizer token pattern): every form that changes data
 * carries a random per-session token, and the server rejects POST requests
 * whose token does not match.
 */

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(): void
{
    $submitted = $_POST['csrf_token'] ?? null;
    $expected = $_SESSION['csrf_token'] ?? null;

    if (!is_string($submitted) || !is_string($expected) || !hash_equals($expected, $submitted)) {
        abort(400, 'Your session has expired or the form is no longer valid. Please go back, reload the page and try again.');
    }
}
