<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/src/bootstrap.php';

require_admin();
require_post();
verify_csrf_token();

$timeSlotId = request_int($_POST, 'time_slot_id');
$timeSlot = $timeSlotId === null ? null : find_time_slot($timeSlotId);

if ($timeSlot === null) {
    flash('error', 'That time slot no longer exists.');
    redirect('admin/time-slots.php');
}

// Reservations keep a reference to their slot, so a slot in use cannot be deleted
// (also enforced by the database foreign key).
if (count_reservations_for_time_slot($timeSlotId) > 0) {
    flash('error', 'This time slot has reservations and cannot be deleted.');
    redirect('admin/time-slots.php');
}

try {
    delete_time_slot($timeSlotId);
    flash('success', sprintf('Time slot %s was deleted.', format_time_slot($timeSlot)));
} catch (mysqli_sql_exception $exception) {
    if ($exception->getCode() !== DB_ERROR_ROW_IS_REFERENCED) {
        throw $exception;
    }
    flash('error', 'This time slot has reservations and cannot be deleted.');
}

redirect('admin/time-slots.php');
