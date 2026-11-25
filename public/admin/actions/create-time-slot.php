<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/src/bootstrap.php';

require_admin();
require_post();
verify_csrf_token();

$startInput = request_string($_POST, 'start_time', 8);
$endInput = request_string($_POST, 'end_time', 8);
$startTime = parse_time_of_day($startInput);
$endTime = parse_time_of_day($endInput);

$error = null;
if ($startTime === null || $endTime === null) {
    $error = 'Please enter a valid start and end time.';
} elseif ($endTime <= $startTime) {
    $error = 'The end time must be after the start time.';
} elseif (time_slot_overlaps_existing($startTime, $endTime)) {
    $error = 'This time slot overlaps an existing slot.';
}

if ($error === null) {
    try {
        create_time_slot($startTime, $endTime);
        flash('success', sprintf('Time slot %s – %s added.', format_time($startTime), format_time($endTime)));
        redirect('admin/time-slots.php');
    } catch (mysqli_sql_exception $exception) {
        if ($exception->getCode() !== DB_ERROR_DUPLICATE_ENTRY) {
            throw $exception;
        }
        $error = 'A time slot with this start time already exists.';
    }
}

remember_form_state(['start_time' => $startInput, 'end_time' => $endInput], []);
flash('error', $error);
redirect('admin/time-slots.php');
