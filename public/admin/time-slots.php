<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

require_admin();

$timeSlots = get_time_slots_with_usage();
['old' => $oldInput] = pull_form_state();
$hourlyRate = app_config()['hourly_rate'];

render('header', ['title' => 'Time Slots', 'area' => 'admin', 'activeNav' => 'time-slots']);
?>
<header class="page-header">
    <div>
        <h1>Time slots</h1>
        <p class="muted">The hours customers can book on every court. Prices are calculated from the slot length at <?= e(format_money($hourlyRate)) ?> per hour.</p>
    </div>
</header>

<form class="card filter-bar" method="post" action="<?= e(url('admin/actions/create-time-slot.php')) ?>">
    <?= csrf_field() ?>
    <div class="field">
        <label for="start_time">Start time</label>
        <input type="time" id="start_time" name="start_time" required step="60" value="<?= e($oldInput['start_time'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="end_time">End time</label>
        <input type="time" id="end_time" name="end_time" required step="60" value="<?= e($oldInput['end_time'] ?? '') ?>">
    </div>
    <div class="filter-actions">
        <button type="submit" class="button button-primary">Add time slot</button>
    </div>
</form>

<div class="table-wrapper">
    <table class="data-table">
        <thead>
        <tr>
            <th scope="col">Time slot</th>
            <th scope="col" class="numeric">Price</th>
            <th scope="col" class="numeric">Reservations</th>
            <th scope="col" class="actions-column">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($timeSlots as $slot): ?>
            <?php $reservationCount = (int) $slot['reservation_count']; ?>
            <tr>
                <td data-label="Time slot"><?= e(format_time_slot($slot)) ?></td>
                <td data-label="Price" class="numeric"><?= e(format_money(calculate_reservation_price($slot['start_time'], $slot['end_time'], $hourlyRate))) ?></td>
                <td data-label="Reservations" class="numeric"><?= $reservationCount ?></td>
                <td data-label="Actions" class="actions-column">
                    <?php if ($reservationCount > 0): ?>
                        <span class="muted" title="Slots with reservations cannot be deleted">In use</span>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('admin/actions/delete-time-slot.php')) ?>"
                              data-confirm="Delete the <?= e(format_time_slot($slot)) ?> time slot?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="time_slot_id" value="<?= (int) $slot['time_slot_id'] ?>">
                            <button type="submit" class="button button-small button-danger">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($timeSlots === []): ?>
            <tr><td colspan="4" class="empty-row">No time slots yet. Customers cannot book until at least one slot exists.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php render('footer'); ?>
