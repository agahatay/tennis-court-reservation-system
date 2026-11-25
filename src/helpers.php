<?php

declare(strict_types=1);

/** Escapes a value for safe output in HTML text and attributes. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL path of the public/ directory, e.g. "" when public/ is the document
 * root, or "/tennis-court-reservation-system/public" under XAMPP htdocs.
 * Derived from the executing script, so no configuration is needed.
 */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $base = '';
    $publicDir = realpath(BASE_PATH . '/public');
    $scriptFile = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if ($publicDir !== false && $scriptFile !== false) {
        $publicDir = str_replace('\\', '/', $publicDir);
        $scriptFile = str_replace('\\', '/', $scriptFile);

        if (str_starts_with(strtolower($scriptFile), strtolower($publicDir . '/'))) {
            $relative = substr($scriptFile, strlen($publicDir));          // e.g. "/admin/dashboard.php"
            if (str_ends_with(strtolower($scriptName), strtolower($relative))) {
                $base = rtrim(substr($scriptName, 0, -strlen($relative)), '/');
            }
        }
    }

    return $base;
}

/**
 * Builds a URL to a file inside public/. Empty query values are omitted.
 *
 * @param array<string, scalar|null> $query
 */
function url(string $path = '', array $query = []): string
{
    $url = base_url() . '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($value): bool => $value !== null && $value !== '');

    if ($query !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    return $url;
}

/** URL of a file in public/assets/, with a cache-busting version parameter. */
function asset_url(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/public/assets/' . $path;

    return url('assets/' . $path, is_file($file) ? ['v' => (string) filemtime($file)] : []);
}

/**
 * Redirects with "303 See Other", the correct status after handling a POST
 * (Post/Redirect/Get), and stops execution.
 *
 * @param array<string, scalar|null> $query
 */
function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query), true, 303);
    exit;
}

/** Renders a template from templates/ with the given variables in scope. */
function render(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require BASE_PATH . '/templates/' . $template . '.php';
}

/** Sends an error page with the given HTTP status and stops execution. */
function abort(int $status, string $message): never
{
    http_response_code($status);
    render('error', ['status' => $status, 'message' => $message, 'details' => null]);
    exit;
}

function handle_uncaught_exception(Throwable $exception): void
{
    error_log(sprintf(
        "Uncaught %s: %s in %s:%d\n%s",
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    ));

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    $showDetails = false;
    try {
        $showDetails = is_development();
    } catch (Throwable) {
        // Configuration itself is broken; fall back to the generic message.
    }

    if (!headers_sent()) {
        http_response_code(500);
    }
    render('error', [
        'status' => 500,
        'message' => 'Something went wrong on our side. Please try again later.',
        'details' => $showDetails ? (string) $exception : null,
    ]);
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    // All CSS and JavaScript are served from files; no inline scripts or styles are needed.
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
        . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
}

/** Reads a trimmed string from a request array ($_GET / $_POST); non-strings become ''. */
function request_string(array $source, string $key, int $maxLength = 255): string
{
    $value = $source[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }

    return trim(mb_substr($value, 0, $maxLength));
}

/** Reads a positive integer id from a request array, or null if absent / invalid. */
function request_int(array $source, string $key): ?int
{
    $value = $source[$key] ?? null;
    if (is_string($value) && preg_match('/^[1-9][0-9]{0,8}$/', $value) === 1) {
        return (int) $value;
    }

    return null;
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        abort(405, 'This action must be submitted using a form.');
    }
}

function now(): DateTimeImmutable
{
    return new DateTimeImmutable('now');
}

function format_money(float|int|string $amount): string
{
    return number_format((float) $amount, 2, '.', ',') . ' ' . app_config()['currency'];
}

/** "08:00:00" -> "08:00" */
function format_time(string $time): string
{
    return substr($time, 0, 5);
}

/** @param array{start_time: string, end_time: string} $slot */
function format_time_slot(array $slot): string
{
    return format_time($slot['start_time']) . ' – ' . format_time($slot['end_time']);
}

/** "2026-11-25" -> "Wed, 25 Nov 2026" */
function format_date(string $date): string
{
    $parsed = parse_iso_date($date);

    return $parsed === null ? $date : $parsed->format('D, d M Y');
}

/**
 * Admin pages that a form may send the user back to after an action. Anything
 * else falls back to $default, which prevents open redirects.
 */
function safe_admin_return_path(string $path, string $default = 'dashboard.php'): string
{
    $pattern = '/^(dashboard|reservations|schedule|payments|time-slots)\.php(\?[A-Za-z0-9_\-=&%.+]*)?$/';

    return preg_match($pattern, $path) === 1 ? $path : $default;
}

/** The current admin page and query string, for use as a form's return_to value. */
function current_admin_page(): string
{
    $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php'));
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');

    return safe_admin_return_path($query === '' ? $page : $page . '?' . $query);
}
