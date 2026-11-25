<?php

declare(strict_types=1);

const CUSTOMER_NAME_MAX_LENGTH = 50;
const PHONE_MIN_DIGITS = 10;
const PHONE_MAX_DIGITS = 15;

/** Parses a strict YYYY-MM-DD date; rejects impossible dates such as 2025-02-31. */
function parse_iso_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return ($date !== false && $date->format('Y-m-d') === $value) ? $date : null;
}

/** Parses "HH:MM" or "HH:MM:SS" and returns the time as "HH:MM:SS", or null. */
function parse_time_of_day(string $value): ?string
{
    if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(?::([0-5][0-9]))?$/', $value, $parts) !== 1) {
        return null;
    }

    return sprintf('%s:%s:%s', $parts[1], $parts[2], $parts[3] ?? '00');
}

/** Trims a customer name and collapses internal whitespace (including newlines). */
function normalize_customer_name(string $name): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $name));
}

/** @return string|null Error message, or null when valid. */
function validate_customer_name(string $name): ?string
{
    if ($name === '') {
        return 'Please enter your name.';
    }
    if (mb_strlen($name) > CUSTOMER_NAME_MAX_LENGTH) {
        return 'Name must be ' . CUSTOMER_NAME_MAX_LENGTH . ' characters or fewer.';
    }

    return null;
}

/**
 * Normalizes a phone number to E.164 format, e.g. "+905321234567".
 *
 * Turkish numbers written in any common national format are treated as the
 * same number:
 *   "0532 123 45 67", "532 123 45 67", "90 532 123 45 67",
 *   "+90 (532) 123-45-67", "0090 532 123 45 67"  ->  "+905321234567"
 * Numbers written with an explicit international prefix ("+" or "00") keep
 * their own country code. Spaces, dashes, dots and parentheses are ignored.
 *
 * The same rules are implemented in SQL in
 * database/migrations/001-upgrade-legacy-schema.sql for existing records.
 *
 * @return string|null The normalized number, or null if it is not a valid phone number.
 */
function normalize_phone(string $phone): ?string
{
    $phone = trim($phone);
    if ($phone === '' || strlen($phone) > 20 || preg_match('/^\+?[0-9 ().\-]+$/', $phone) !== 1) {
        return null;
    }

    $digits = (string) preg_replace('/\D+/', '', $phone);

    if (str_starts_with($phone, '+')) {
        $international = $digits;                       // +90 532 ..., +49 ...
    } elseif (str_starts_with($digits, '00')) {
        $international = substr($digits, 2);            // 0090 532 ...
    } elseif (strlen($digits) === 11 && $digits[0] === '0') {
        $international = '90' . substr($digits, 1);     // 0532 123 45 67
    } elseif (strlen($digits) === 10 && $digits[0] !== '0') {
        $international = '90' . $digits;                // 532 123 45 67
    } elseif (strlen($digits) === 12 && str_starts_with($digits, '90')) {
        $international = $digits;                       // 90 532 123 45 67
    } else {
        return null;
    }

    $length = strlen($international);
    if ($length < PHONE_MIN_DIGITS || $length > PHONE_MAX_DIGITS || $international[0] === '0') {
        return null;
    }
    if (str_starts_with($international, '90') && $length !== 12) {
        return null;                                    // Turkish numbers have 10 national digits
    }

    return '+' . $international;
}

/**
 * For admin searches: if the term looks like a (partial) phone number with at
 * least 6 digits, returns its national digits without a leading "0", "90",
 * "+90" or "0090", ready to be matched against normalized numbers.
 */
function phone_search_digits(string $term): ?string
{
    $term = trim($term);
    if (preg_match('/^\+?[0-9 ().\-]+$/', $term) !== 1) {
        return null;
    }

    $digits = (string) preg_replace('/\D+/', '', $term);
    $digits = (string) preg_replace('/^(0090|90|0)/', '', $digits);

    return strlen($digits) >= 6 ? $digits : null;
}

/** @return string|null Error message, or null when valid. */
function validate_phone(string $phone): ?string
{
    if ($phone === '') {
        return 'Please enter your phone number.';
    }
    if (normalize_phone($phone) === null) {
        return 'Please enter a valid phone number, e.g. 0532 123 45 67 or +90 532 123 45 67.';
    }

    return null;
}
