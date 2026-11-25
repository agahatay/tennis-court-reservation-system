<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

require_admin();

$now = now();
$today = $now->format('Y-m-d');

$todayCount = count_reservations_on_date($today);
$totalCount = count_all_reservations();
$mostBookedCourt = find_most_booked_court();
$income = get_income_summary();
$upcomingReservations = get_upcoming_reservations($now, 5);

render('header', ['title' => 'Dashboard', 'area' => 'admin', 'activeNav' => 'dashboard']);
?>
<header class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p class="muted"><?= e(format_date($today)) ?></p>
    </div>
    <a class="button button-secondary" href="<?= e(url('admin/schedule.php')) ?>">Today's schedule</a>
</header>

<section class="stat-grid" aria-label="Reservation statistics">
    <article class="stat-card">
        <h2 class="stat-label">Reservations today</h2>
        <p class="stat-value"><?= $todayCount ?></p>
    </article>
    <article class="stat-card">
        <h2 class="stat-label">Total reservations</h2>
        <p class="stat-value"><?= $totalCount ?></p>
    </article>
    <article class="stat-card">
        <h2 class="stat-label">Most booked court</h2>
        <p class="stat-value"><?= e($mostBookedCourt['court_name'] ?? '–') ?></p>
        <p class="stat-note"><?= (int) ($mostBookedCourt['reservation_count'] ?? 0) ?> bookings</p>
    </article>
</section>

<section class="stat-grid" aria-label="Income summary">
    <article class="stat-card">
        <h2 class="stat-label">Total income</h2>
        <p class="stat-value"><?= e(format_money($income['total_amount'])) ?></p>
        <p class="stat-note"><?= $totalCount ?> reservations</p>
    </article>
    <article class="stat-card stat-card-paid">
        <h2 class="stat-label">Paid income</h2>
        <p class="stat-value"><?= e(format_money($income['paid_amount'])) ?></p>
        <p class="stat-note"><?= $income['paid_count'] ?> paid</p>
    </article>
    <article class="stat-card stat-card-unpaid">
        <h2 class="stat-label">Unpaid income</h2>
        <p class="stat-value"><?= e(format_money($income['unpaid_amount'])) ?></p>
        <p class="stat-note">
            <?= $income['unpaid_count'] ?> unpaid ·
            <a href="<?= e(url('admin/payments.php', ['status' => 'unpaid'])) ?>">view</a>
        </p>
    </article>
</section>

<section class="section">
    <div class="section-header">
        <h2>Next 5 reservations</h2>
        <a href="<?= e(url('admin/reservations.php')) ?>">All reservations →</a>
    </div>
    <?php render('reservation-table', [
        'reservations' => $upcomingReservations,
        'showActions' => false,
        'emptyMessage' => 'No upcoming reservations.',
    ]); ?>
</section>
<?php render('footer'); ?>
