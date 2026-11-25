<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

require_admin();

$now = now();
$date = request_string($_GET, 'date', 10);
$selectedDate = parse_iso_date($date) ?? new DateTimeImmutable($now->format('Y-m-d'));
$date = $selectedDate->format('Y-m-d');

$courts = get_all_courts();
$timeSlots = get_all_time_slots();

// Index the day's reservations by "court id : time slot id" for O(1) lookup per cell.
$reservationsByCell = [];
foreach (get_reservations_on_date($date) as $reservation) {
    $reservationsByCell[$reservation['court_id'] . ':' . $reservation['time_slot_id']] = $reservation;
}

$paidCount = count(array_filter($reservationsByCell, static fn (array $r): bool => (int) $r['is_paid'] === 1));
$bookedCount = count($reservationsByCell);
$totalCells = count($courts) * count($timeSlots);

render('header', ['title' => 'Daily Schedule', 'area' => 'admin', 'activeNav' => 'schedule']);
?>
<header class="page-header">
    <div>
        <h1>Daily schedule</h1>
        <p class="muted"><?= e(format_date($date)) ?></p>
    </div>
    <form class="schedule-date-picker" method="get" action="<?= e(url('admin/schedule.php')) ?>" data-auto-submit>
        <a class="button button-ghost" href="<?= e(url('admin/schedule.php', ['date' => $selectedDate->modify('-1 day')->format('Y-m-d')])) ?>" aria-label="Previous day">←</a>
        <label class="visually-hidden" for="date">Date</label>
        <input type="date" id="date" name="date" value="<?= e($date) ?>">
        <a class="button button-ghost" href="<?= e(url('admin/schedule.php', ['date' => $selectedDate->modify('+1 day')->format('Y-m-d')])) ?>" aria-label="Next day">→</a>
        <a class="button button-ghost" href="<?= e(url('admin/schedule.php')) ?>">Today</a>
        <noscript><button type="submit" class="button button-secondary">Show</button></noscript>
    </form>
</header>

<div class="schedule-summary">
    <ul class="legend" aria-label="Legend">
        <li><span class="legend-swatch cell-paid"></span>Paid</li>
        <li><span class="legend-swatch cell-unpaid"></span>Unpaid</li>
        <li><span class="legend-swatch cell-available"></span>Available</li>
    </ul>
    <p class="muted">
        <?= $bookedCount ?> of <?= $totalCells ?> slots booked
        · <?= $paidCount ?> paid · <?= $bookedCount - $paidCount ?> unpaid
    </p>
</div>

<?php if ($courts === [] || $timeSlots === []): ?>
    <div class="alert alert-info">Add courts and time slots to see the schedule.</div>
<?php else: ?>
    <div class="table-wrapper">
        <table class="schedule-table">
            <thead>
            <tr>
                <th scope="col">Time</th>
                <?php foreach ($courts as $court): ?>
                    <th scope="col"><?= e($court['court_name']) ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($timeSlots as $slot): ?>
                <tr>
                    <th scope="row"><?= e(format_time_slot($slot)) ?></th>
                    <?php foreach ($courts as $court): ?>
                        <?php $reservation = $reservationsByCell[$court['court_id'] . ':' . $slot['time_slot_id']] ?? null; ?>
                        <?php if ($reservation === null): ?>
                            <td class="cell-available"><span class="visually-hidden">Available</span></td>
                        <?php else: ?>
                            <?php $isPaid = (int) $reservation['is_paid'] === 1; ?>
                            <td class="<?= $isPaid ? 'cell-paid' : 'cell-unpaid' ?>"
                                title="<?= e($reservation['customer_name'] . ' · ' . $reservation['customer_phone'] . ' · ' . format_money($reservation['price'])) ?>">
                                <span class="cell-name"><?= e($reservation['customer_name']) ?></span>
                                <span class="cell-status"><?= $isPaid ? 'Paid' : 'Unpaid' ?></span>
                            </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php render('footer'); ?>
