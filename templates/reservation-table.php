<?php
/**
 * Reservation table shared by the dashboard, reservations and payments pages.
 *
 * @var list<array<string, mixed>> $reservations
 * @var bool                       $showActions   show payment / delete buttons
 * @var bool                       $allowDelete   include the delete button
 * @var string                     $emptyMessage
 */
$showActions ??= true;
$allowDelete ??= true;
$emptyMessage ??= 'No reservations found.';
$returnTo = current_admin_page();
?>
<div class="table-wrapper">
    <table class="data-table">
        <thead>
        <tr>
            <th scope="col">Date</th>
            <th scope="col">Time</th>
            <th scope="col">Court</th>
            <th scope="col">Customer</th>
            <th scope="col">Phone</th>
            <th scope="col" class="numeric">Price</th>
            <th scope="col">Payment</th>
            <?php if ($showActions): ?><th scope="col" class="actions-column">Actions</th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($reservations as $reservation): ?>
            <?php
            $reservationId = (int) $reservation['reservation_id'];
            $isPaid = (int) $reservation['is_paid'] === 1;
            ?>
            <tr>
                <td data-label="Date"><?= e(format_date($reservation['reservation_date'])) ?></td>
                <td data-label="Time"><?= e(format_time_slot($reservation)) ?></td>
                <td data-label="Court"><?= e($reservation['court_name']) ?></td>
                <td data-label="Customer"><?= e($reservation['customer_name']) ?></td>
                <td data-label="Phone"><?= e($reservation['customer_phone']) ?></td>
                <td data-label="Price" class="numeric"><?= e(format_money($reservation['price'])) ?></td>
                <td data-label="Payment">
                    <span class="badge <?= $isPaid ? 'badge-paid' : 'badge-unpaid' ?>"><?= $isPaid ? 'Paid' : 'Unpaid' ?></span>
                </td>
                <?php if ($showActions): ?>
                    <td data-label="Actions" class="actions-column">
                        <div class="row-actions">
                            <form method="post" action="<?= e(url('admin/actions/update-payment-status.php')) ?>"
                                  data-confirm="<?= e($isPaid ? 'Mark this reservation as UNPAID?' : 'Mark this reservation as PAID?') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="reservation_id" value="<?= $reservationId ?>">
                                <input type="hidden" name="is_paid" value="<?= $isPaid ? '0' : '1' ?>">
                                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                                <button type="submit" class="button button-small <?= $isPaid ? 'button-secondary' : 'button-primary' ?>">
                                    <?= $isPaid ? 'Mark unpaid' : 'Mark paid' ?>
                                </button>
                            </form>
                            <?php if ($allowDelete): ?>
                                <form method="post" action="<?= e(url('admin/actions/delete-reservation.php')) ?>"
                                      data-confirm="Delete this reservation? This cannot be undone.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reservation_id" value="<?= $reservationId ?>">
                                    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                                    <button type="submit" class="button button-small button-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if ($reservations === []): ?>
            <tr><td colspan="<?= $showActions ? 8 : 7 ?>" class="empty-row"><?= e($emptyMessage) ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
