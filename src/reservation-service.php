<?php

declare(strict_types=1);

/**
 * Reservation business logic: input validation, server-side pricing and the
 * transactional booking itself. Kept free of HTML so it can be tested directly.
 */

/** "HH:MM[:SS]" -> minutes since midnight */
function time_to_minutes(string $time): int
{
    [$hours, $minutes] = array_map('intval', explode(':', $time));

    return $hours * 60 + $minutes;
}

/**
 * Price of a time slot, calculated from its length and the hourly rate.
 * Returned as a DECIMAL-compatible string (e.g. "3000.00").
 */
function calculate_reservation_price(string $startTime, string $endTime, float $hourlyRate): string
{
    $durationInMinutes = time_to_minutes($endTime) - time_to_minutes($startTime);
    if ($durationInMinutes <= 0) {
        throw new InvalidArgumentException('A time slot must end after it starts.');
    }

    return number_format($hourlyRate * $durationInMinutes / 60, 2, '.', '');
}

/** True if the slot on this date has already started (it can no longer be booked). */
function has_time_slot_started(string $reservationDate, string $startTime, DateTimeImmutable $now): bool
{
    $today = $now->format('Y-m-d');

    return $reservationDate < $today
        || ($reservationDate === $today && $startTime <= $now->format('H:i:s'));
}

/**
 * Validates a booking request and creates the reservation.
 *
 * The price is always calculated here from the configured hourly rate; any
 * price sent by the browser is ignored. Double bookings are prevented by the
 * UNIQUE (court_id, reservation_date, time_slot_id) constraint, which also
 * covers two customers submitting the same slot at the same moment.
 *
 * @param array<string, mixed> $input raw request data ($_POST)
 * @return array{success: true, reservation: array<string, mixed>}
 *       | array{success: false, errors: array<string, string>}
 */
function book_reservation(array $input, DateTimeImmutable $now): array
{
    $errors = [];

    $courtId = request_int($input, 'court_id');
    $court = $courtId === null ? null : find_court($courtId);
    if ($court === null) {
        $errors['court_id'] = 'Please select a valid court.';
    }

    $reservationDate = request_string($input, 'reservation_date', 10);
    if (parse_iso_date($reservationDate) === null) {
        $errors['reservation_date'] = 'Please select a valid date.';
    } elseif ($reservationDate < $now->format('Y-m-d')) {
        $errors['reservation_date'] = 'Reservations cannot be made for past dates.';
    }

    $timeSlotId = request_int($input, 'time_slot_id');
    $timeSlot = $timeSlotId === null ? null : find_time_slot($timeSlotId);
    if ($timeSlot === null) {
        $errors['time_slot_id'] = 'Please select an available time slot.';
    } elseif (!isset($errors['reservation_date']) && has_time_slot_started($reservationDate, $timeSlot['start_time'], $now)) {
        $errors['time_slot_id'] = 'This time slot has already started. Please choose a later one.';
    }

    $name = normalize_customer_name(request_string($input, 'name', 200));
    $nameError = validate_customer_name($name);
    if ($nameError !== null) {
        $errors['name'] = $nameError;
    }

    $phone = request_string($input, 'phone', 50);
    $phoneError = validate_phone($phone);
    if ($phoneError !== null) {
        $errors['phone'] = $phoneError;
    }

    if ($errors !== []) {
        return ['success' => false, 'errors' => $errors];
    }

    $price = calculate_reservation_price($timeSlot['start_time'], $timeSlot['end_time'], app_config()['hourly_rate']);

    try {
        $reservationId = db_transaction(static function () use ($name, $phone, $courtId, $timeSlotId, $reservationDate, $price): int {
            $customerId = find_or_create_customer($name, $phone);
            return insert_reservation($customerId, $courtId, $timeSlotId, $reservationDate, $price);
        });
    } catch (mysqli_sql_exception $exception) {
        if ($exception->getCode() === DB_ERROR_DUPLICATE_ENTRY) {
            return [
                'success' => false,
                'errors' => ['time_slot_id' => 'Sorry, this time slot has just been booked. Please choose another one.'],
            ];
        }
        throw $exception;
    }

    return [
        'success' => true,
        'reservation' => [
            'reservation_id' => $reservationId,
            'court_name' => $court['court_name'],
            'reservation_date' => $reservationDate,
            'start_time' => $timeSlot['start_time'],
            'end_time' => $timeSlot['end_time'],
            'price' => $price,
        ],
    ];
}
