<?php

declare(strict_types=1);

/**
 * Returns the id of the customer making a booking, creating a new customer
 * record when no matching one exists.
 *
 * Matching rule: a booking belongs to an existing customer when BOTH
 *   - the normalized phone number is the same ("0532…", "532…" and "+90532…"
 *     are the same number, see normalize_phone()), and
 *   - the name is the same, ignoring letter case and extra spaces.
 *
 * A different name on the same phone number creates a separate customer,
 * because different people (e.g. family members) can share a phone. Existing
 * records are never merged automatically; when several records already match,
 * the oldest one is used. The phone is stored exactly as entered for display,
 * and the normalized form is stored alongside it for matching and searching.
 */
function find_or_create_customer(string $name, string $phone): int
{
    $normalizedPhone = normalize_phone($phone);
    if ($normalizedPhone === null) {
        throw new InvalidArgumentException('Phone number must be validated before saving.');
    }

    $customerId = db_fetch_value(
        'SELECT customer_id FROM customers
         WHERE phone_normalized = ? AND name = ?
         ORDER BY customer_id
         LIMIT 1',
        [$normalizedPhone, $name]
    );

    if ($customerId !== null) {
        return (int) $customerId;
    }

    return db_insert(
        'INSERT INTO customers (name, phone, phone_normalized) VALUES (?, ?, ?)',
        [$name, $phone, $normalizedPhone]
    );
}
