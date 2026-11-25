<?php

declare(strict_types=1);

/**
 * Unit tests for the pure functions (no database or web server needed).
 * Run with: php tests/unit-tests.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

$passed = 0;
$failed = 0;

function assert_same(string $description, mixed $expected, mixed $actual): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        echo "PASS  $description\n";
    } else {
        $failed++;
        echo "FAIL  $description\n      expected: " . var_export($expected, true) . "\n      actual:   " . var_export($actual, true) . "\n";
    }
}

// --- Phone normalization: Turkish formats are equivalent -----------------------
$sameNumber = [
    '0532 123 45 67', '05321234567', '532 123 45 67', '5321234567',
    '+90 532 123 45 67', '+905321234567', '90 532 123 45 67', '0090 532 123 45 67',
    '+90 (532) 123-45-67', '0532.123.45.67', '  0532 123 45 67  ',
];
foreach ($sameNumber as $input) {
    assert_same("normalize_phone('$input')", '+905321234567', normalize_phone($input));
}
assert_same('landline 0212 555 12 34', '+902125551234', normalize_phone('0212 555 12 34'));
assert_same('international number keeps its country code', '+491701234567', normalize_phone('+49 170 1234567'));
assert_same('international 00 prefix', '+491701234567', normalize_phone('0049 170 1234567'));

$invalid = [
    '' => 'empty', '12345' => 'too short', '053212345678' => '12 digits with leading 0',
    '+90 532 123 45' => 'Turkish number too short', '+90 532 123 45 678' => 'Turkish number too long',
    '0532 123 45 6a' => 'letters', '+0532 123 45 67' => 'country code starting with 0',
    '+1234567890123456' => 'over 15 digits', '0532 123 45 67 ext 2' => 'extension text',
    '++905321234567' => 'double plus',
];
foreach ($invalid as $input => $reason) {
    assert_same("normalize_phone rejects $reason", null, normalize_phone((string) $input));
}
assert_same('validate_phone accepts a valid number', null, validate_phone('0532 123 45 67'));
assert_same('validate_phone rejects an invalid number', true, is_string(validate_phone('12345')));
assert_same('validate_phone requires a value', 'Please enter your phone number.', validate_phone(''));

// --- Phone search terms ----------------------------------------------------------
assert_same('phone search: 0532 123', '532123', phone_search_digits('0532 123'));
assert_same('phone search: +90532123', '532123', phone_search_digits('+90532123'));
assert_same('phone search: too few digits', null, phone_search_digits('0532'));
assert_same('phone search: names are not phone terms', null, phone_search_digits('Demo 123456'));

// --- Customer names --------------------------------------------------------------
assert_same('name whitespace collapsed', 'Ayşe Yılmaz', normalize_customer_name("  Ayşe \n  Yılmaz "));
assert_same('empty name rejected', 'Please enter your name.', validate_customer_name(''));
assert_same('50 multibyte characters accepted', null, validate_customer_name(str_repeat('ş', 50)));
assert_same('51 characters rejected', true, is_string(validate_customer_name(str_repeat('a', 51))));

// --- Dates and times -------------------------------------------------------------
assert_same('valid date', '2026-02-28', parse_iso_date('2026-02-28')?->format('Y-m-d'));
assert_same('impossible date 2026-02-30', null, parse_iso_date('2026-02-30'));
assert_same('non-padded date', null, parse_iso_date('2026-2-3'));
assert_same('garbage date', null, parse_iso_date('tomorrow'));
assert_same('time HH:MM', '08:30:00', parse_time_of_day('08:30'));
assert_same('time HH:MM:SS', '21:00:59', parse_time_of_day('21:00:59'));
assert_same('time 24:00 rejected', null, parse_time_of_day('24:00'));

$now = new DateTimeImmutable('2026-11-25 14:30:00');
assert_same('slot later today has not started', false, has_time_slot_started('2026-11-25', '15:00:00', $now));
assert_same('slot earlier today has started', true, has_time_slot_started('2026-11-25', '14:00:00', $now));
assert_same('slot starting now has started', true, has_time_slot_started('2026-11-25', '14:30:00', $now));
assert_same('slot tomorrow has not started', false, has_time_slot_started('2026-11-26', '08:00:00', $now));
assert_same('slot yesterday has started', true, has_time_slot_started('2026-11-24', '20:00:00', $now));

// --- Pricing ---------------------------------------------------------------------
assert_same('1 hour at 3000', '3000.00', calculate_reservation_price('08:00:00', '09:00:00', 3000.0));
assert_same('90 minutes at 3000', '4500.00', calculate_reservation_price('21:00:00', '22:30:00', 3000.0));
assert_same('30 minutes at 2500', '1250.00', calculate_reservation_price('10:00', '10:30', 2500.0));
$threw = false;
try {
    calculate_reservation_price('10:00', '10:00', 3000.0);
} catch (InvalidArgumentException) {
    $threw = true;
}
assert_same('zero-length slot throws', true, $threw);

// --- Redirect whitelist ----------------------------------------------------------
assert_same('allowed return path', 'payments.php?status=unpaid', safe_admin_return_path('payments.php?status=unpaid'));
foreach (['https://evil.example', '//evil.example', '../index.php', "payments.php\r\nLocation: x", 'login.php', ''] as $path) {
    assert_same('rejected return path ' . json_encode($path), 'dashboard.php', safe_admin_return_path($path));
}

// --- Login throttling helpers ------------------------------------------------------
assert_same('username hash is case-insensitive', hash_login_username('Admin'), hash_login_username(' admin '));
assert_same('username hash is SHA-256 hex', 64, strlen(hash_login_username('admin')));

echo "\nRESULT: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
