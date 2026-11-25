<?php

declare(strict_types=1);

/**
 * Creates an administrator account, or resets its password.
 *
 * Usage:
 *   php scripts/create-admin.php <username>            create a new admin
 *   php scripts/create-admin.php <username> --reset    change an existing admin's password
 *
 * The password is read from the ADMIN_PASSWORD environment variable if set,
 * otherwise it is prompted for. It is stored only as a password_hash() hash.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

const MIN_PASSWORD_LENGTH = 10;

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);

    return $line === false ? '' : rtrim($line, "\r\n");
}

$username = $argv[1] ?? '';
$reset = in_array('--reset', array_slice($argv, 2), true);

if (preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $username) !== 1) {
    fail("Usage: php scripts/create-admin.php <username> [--reset]\n"
        . 'The username must be 3-50 characters: letters, digits, ".", "_" or "-".');
}

$existingAdmin = find_admin_by_username($username);
if ($existingAdmin !== null && !$reset) {
    fail("An admin named \"$username\" already exists. Use --reset to change the password.");
}
if ($existingAdmin === null && $reset) {
    fail("No admin named \"$username\" exists.");
}

$password = getenv('ADMIN_PASSWORD');
if ($password === false || $password === '') {
    fwrite(STDOUT, "Note: the password will be visible while you type it.\n");
    $password = prompt('Password: ');
    if ($password !== prompt('Repeat password: ')) {
        fail('The passwords do not match.');
    }
}

if (strlen($password) < MIN_PASSWORD_LENGTH) {
    fail('The password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

if ($existingAdmin === null) {
    create_admin($username, $passwordHash);
    fwrite(STDOUT, "Admin \"$username\" created.\n");
} else {
    update_admin_password_hash((int) $existingAdmin['admin_id'], $passwordHash);
    fwrite(STDOUT, "Password for \"$username\" updated.\n");
}
