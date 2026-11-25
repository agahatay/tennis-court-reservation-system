<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/src/bootstrap.php';

require_admin();
require_post();
verify_csrf_token();

$returnTo = safe_admin_return_path(request_string($_POST, 'return_to', 500), 'reservations.php');
$reservationId = request_int($_POST, 'reservation_id');

if ($reservationId !== null && delete_reservation($reservationId)) {
    flash('success', "Reservation #$reservationId was deleted.");
} else {
    flash('error', 'That reservation no longer exists.');
}

redirect('admin/' . $returnTo);
