<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

require_admin();

$status = request_string($_GET, 'status', 10);
if (!in_array($status, PAYMENT_FILTERS, true)) {
    $status = 'all';
}

$income = get_income_summary();
$reservations = search_reservations(['payment' => $status], 'desc');

$tabs = ['all' => 'All', 'paid' => 'Paid', 'unpaid' => 'Unpaid'];

render('header', ['title' => 'Payments', 'area' => 'admin', 'activeNav' => 'payments']);
?>
<header class="page-header">
    <div>
        <h1>Payments</h1>
        <p class="muted">Income from all reservations, newest first.</p>
    </div>
</header>

<section class="stat-grid" aria-label="Income summary">
    <article class="stat-card">
        <h2 class="stat-label">Total income</h2>
        <p class="stat-value"><?= e(format_money($income['total_amount'])) ?></p>
        <p class="stat-note"><?= $income['paid_count'] + $income['unpaid_count'] ?> reservations</p>
    </article>
    <article class="stat-card stat-card-paid">
        <h2 class="stat-label">Paid</h2>
        <p class="stat-value"><?= e(format_money($income['paid_amount'])) ?></p>
        <p class="stat-note"><?= $income['paid_count'] ?> reservations</p>
    </article>
    <article class="stat-card stat-card-unpaid">
        <h2 class="stat-label">Unpaid</h2>
        <p class="stat-value"><?= e(format_money($income['unpaid_amount'])) ?></p>
        <p class="stat-note"><?= $income['unpaid_count'] ?> reservations</p>
    </article>
</section>

<nav class="tabs" aria-label="Filter by payment status">
    <?php foreach ($tabs as $value => $label): ?>
        <a href="<?= e(url('admin/payments.php', ['status' => $value])) ?>"
           class="tab<?= $status === $value ? ' is-active' : '' ?>"<?= $status === $value ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php render('reservation-table', [
    'reservations' => $reservations,
    'allowDelete' => false,
    'emptyMessage' => 'No reservations for this filter.',
]); ?>
<?php render('footer'); ?>
