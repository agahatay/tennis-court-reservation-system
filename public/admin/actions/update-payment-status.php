<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/src/bootstrap.php';

require_admin();
require_post();
verify_csrf_token();

$returnTo = safe_admin_return_path(request_string($_POST, 'return_to', 500), 'reservations.php');
$reservationId = request_int($_POST, 'reservation_id');
$requestedStatus = request_string($_POST, 'is_paid', 1);

// The form sends the target status (not a "toggle"), so a double submit is harmless.
if (!in_array($requestedStatus, ['0', '1'], true)) {
    flash('error', 'Invalid payment status.');
    redirect('admin/' . $returnTo);
}

$reservation = $reservationId === null ? null : find_reservation($reservationId);
if ($reservation === null) {
    flash('error', 'That reservation no longer exists.');
    redirect('admin/' . $returnTo);
}

$isPaid = $requestedStatus === '1';
set_reservation_payment_status($reservationId, $isPaid);
flash('success', sprintf('Reservation #%d marked as %s.', $reservationId, $isPaid ? 'paid' : 'unpaid'));

redirect('admin/' . $returnTo);
