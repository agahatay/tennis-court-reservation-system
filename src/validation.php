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
 * Accepts digits with optional spaces, dashes, dots, parentheses and a
 * leading "+", containing 10 to 15 digits in total.
 *
 * @return string|null Error message, or null when valid.
 */
function validate_phone(string $phone): ?string
{
    if ($phone === '') {
        return 'Please enter your phone number.';
    }

    $digitCount = strlen((string) preg_replace('/\D+/', '', $phone));
    $hasOnlyAllowedCharacters = preg_match('/^\+?[0-9 ().\-]+$/', $phone) === 1;

    if (!$hasOnlyAllowedCharacters || $digitCount < PHONE_MIN_DIGITS || $digitCount > PHONE_MAX_DIGITS || strlen($phone) > 20) {
        return 'Please enter a valid phone number (' . PHONE_MIN_DIGITS . '–' . PHONE_MAX_DIGITS . ' digits).';
    }

    return null;
}
