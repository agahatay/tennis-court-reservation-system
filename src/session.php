<?php

declare(strict_types=1);

function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

/**
 * Starts the session with hardened settings:
 * - strict mode rejects session ids the server did not create (session fixation)
 * - cookies only (no session id in URLs), HttpOnly (not readable by JavaScript)
 * - SameSite=Lax (not sent on cross-site POSTs), Secure when served over HTTPS
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_name('tcrs_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_url() === '' ? '/' : base_url() . '/',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/** Queues a one-time message ("success", "error" or "info") for the next page. */
function flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type: string, message: string}> */
function pull_flash_messages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}

/**
 * Keeps submitted values and validation errors for one redirect, so a form can
 * be re-displayed with the user's input after Post/Redirect/Get.
 *
 * @param array<string, string> $oldInput
 * @param array<string, string> $errors
 */
function remember_form_state(array $oldInput, array $errors): void
{
    $_SESSION['form_state'] = ['old' => $oldInput, 'errors' => $errors];
}

/** @return array{old: array<string, string>, errors: array<string, string>} */
function pull_form_state(): array
{
    $state = $_SESSION['form_state'] ?? [];
    unset($_SESSION['form_state']);

    return [
        'old' => is_array($state['old'] ?? null) ? $state['old'] : [],
        'errors' => is_array($state['errors'] ?? null) ? $state['errors'] : [],
    ];
}
