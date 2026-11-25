<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Values are read from real environment variables first, then from the
 * project-level .env file (which lives outside the public web root and is
 * never committed). Defaults are only suitable for local development.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

/**
 * Parses a simple KEY=VALUE .env file. Lines starting with # are comments.
 * Surrounding single or double quotes around a value are removed.
 *
 * @return array<string, string>
 */
function load_env_file(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        $quote = $value[0] ?? '';
        if (strlen($value) >= 2 && ($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
            $value = substr($value, 1, -1);
        }

        $values[$key] = $value;
    }

    return $values;
}

/**
 * Returns the application configuration (loaded once per request).
 *
 * @return array{
 *     env: string,
 *     timezone: string,
 *     db: array{host: string, port: int, name: string, user: string, password: string},
 *     hourly_rate: float,
 *     currency: string,
 *     session_idle_timeout: int
 * }
 */
function app_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $fileValues = load_env_file(BASE_PATH . '/.env');
    $read = static function (string $key, string $default) use ($fileValues): string {
        $fromEnvironment = getenv($key);
        if ($fromEnvironment !== false) {
            return $fromEnvironment;
        }
        return $fileValues[$key] ?? $default;
    };

    $hourlyRate = $read('HOURLY_RATE', '3000');
    if (!is_numeric($hourlyRate) || (float) $hourlyRate <= 0) {
        throw new RuntimeException('Configuration error: HOURLY_RATE must be a positive number.');
    }

    $config = [
        'env' => $read('APP_ENV', 'production'),
        'timezone' => $read('APP_TIMEZONE', 'Europe/Istanbul'),
        'db' => [
            'host' => $read('DB_HOST', '127.0.0.1'),
            'port' => (int) $read('DB_PORT', '3306'),
            'name' => $read('DB_NAME', 'tennis_court_reservation'),
            'user' => $read('DB_USER', 'root'),
            'password' => $read('DB_PASSWORD', ''),
        ],
        'hourly_rate' => (float) $hourlyRate,
        'currency' => $read('CURRENCY', 'TL'),
        'session_idle_timeout' => max(60, (int) $read('SESSION_IDLE_TIMEOUT', '1800')),
    ];

    return $config;
}

function is_development(): bool
{
    return app_config()['env'] === 'development';
}
