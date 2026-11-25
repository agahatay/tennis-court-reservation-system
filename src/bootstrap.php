<?php

declare(strict_types=1);

/**
 * Single entry point for every page and script: loads configuration and
 * libraries, configures error handling and time zone, sends security headers
 * and starts the session.
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/src/config.php';
require_once BASE_PATH . '/src/helpers.php';
require_once BASE_PATH . '/src/database.php';
require_once BASE_PATH . '/src/session.php';
require_once BASE_PATH . '/src/csrf.php';
require_once BASE_PATH . '/src/validation.php';
require_once BASE_PATH . '/src/repositories/admins.php';
require_once BASE_PATH . '/src/repositories/courts.php';
require_once BASE_PATH . '/src/repositories/customers.php';
require_once BASE_PATH . '/src/repositories/time-slots.php';
require_once BASE_PATH . '/src/repositories/reservations.php';
require_once BASE_PATH . '/src/repositories/statistics.php';
require_once BASE_PATH . '/src/auth.php';
require_once BASE_PATH . '/src/reservation-service.php';

// Errors are logged, never shown to visitors (unless APP_ENV=development).
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
ini_set('display_errors', '0');
set_exception_handler('handle_uncaught_exception');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

ini_set('display_errors', is_development() ? '1' : '0');
date_default_timezone_set(app_config()['timezone']);

if (PHP_SAPI !== 'cli') {
    send_security_headers();
    start_secure_session();
}
