<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

require_admin();

$search = request_string($_GET, 'search', 50);
$date = request_string($_GET, 'date', 10);
if ($date !== '' && parse_iso_date($date) === null) {
    $date = '';
}
$payment = request_string($_GET, 'payment', 10);
if (!in_array($payment, PAYMENT_FILTERS, true)) {
    $payment = 'all';
}

$reservations = search_reservations(['search' => $search, 'date' => $date, 'payment' => $payment]);
$isFiltered = $search !== '' || $date !== '' || $payment !== 'all';

render('header', ['title' => 'Reservations', 'area' => 'admin', 'activeNav' => 'reservations']);
?>
<header class="page-header">
    <div>
        <h1>Reservations</h1>
        <p class="muted"><?= count($reservations) ?> <?= count($reservations) === 1 ? 'reservation' : 'reservations' ?><?= $isFiltered ? ' match the filters' : '' ?></p>
    </div>
</header>

<form class="card filter-bar" method="get" action="<?= e(url('admin/reservations.php')) ?>">
    <div class="field">
        <label for="search">Customer name or phone</label>
        <input type="search" id="search" name="search" value="<?= e($search) ?>" maxlength="50" placeholder="Search…">
    </div>
    <div class="field">
        <label for="date">Date</label>
        <input type="date" id="date" name="date" value="<?= e($date) ?>">
    </div>
    <div class="field">
        <label for="payment">Payment status</label>
        <select id="payment" name="payment">
            <option value="all"<?= $payment === 'all' ? ' selected' : '' ?>>All</option>
            <option value="paid"<?= $payment === 'paid' ? ' selected' : '' ?>>Paid</option>
            <option value="unpaid"<?= $payment === 'unpaid' ? ' selected' : '' ?>>Unpaid</option>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="button button-primary">Apply</button>
        <?php if ($isFiltered): ?>
            <a class="button button-ghost" href="<?= e(url('admin/reservations.php')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php render('reservation-table', [
    'reservations' => $reservations,
    'emptyMessage' => $isFiltered ? 'No reservations match these filters.' : 'There are no reservations yet.',
]); ?>
<?php render('footer'); ?>
