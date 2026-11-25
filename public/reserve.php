<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

require_post();
verify_csrf_token();

$result = book_reservation($_POST, now());

$backToForm = [
    'court_id' => request_int($_POST, 'court_id'),
    'date' => request_string($_POST, 'reservation_date', 10),
];

if (!$result['success']) {
    remember_form_state([
        'name' => request_string($_POST, 'name', 200),
        'phone' => request_string($_POST, 'phone', 50),
        'time_slot_id' => request_string($_POST, 'time_slot_id', 10),
    ], $result['errors']);
    flash('error', 'Your reservation could not be created. Please check the details below.');
    redirect('index.php', $backToForm);
}

$reservation = $result['reservation'];
flash('success', sprintf(
    'Reservation #%d confirmed: %s on %s, %s. Price: %s.',
    $reservation['reservation_id'],
    $reservation['court_name'],
    format_date($reservation['reservation_date']),
    format_time_slot($reservation),
    format_money($reservation['price'])
));
redirect('index.php', $backToForm);
