<?php

declare(strict_types=1);

/**
 * Returns the id of the customer with this phone number, creating the
 * customer if none exists.
 *
 * Business rule carried over from the original application: customers are
 * identified by the phone number exactly as entered, and an existing
 * customer's name is not changed by a new booking. This rule (and whether
 * phone numbers should be unique / normalized) is pending review.
 */
function find_or_create_customer(string $name, string $phone): int
{
    $customerId = db_fetch_value(
        'SELECT customer_id FROM customers WHERE phone = ? ORDER BY customer_id LIMIT 1',
        [$phone]
    );

    if ($customerId !== null) {
        return (int) $customerId;
    }

    return db_insert('INSERT INTO customers (name, phone) VALUES (?, ?)', [$name, $phone]);
}
