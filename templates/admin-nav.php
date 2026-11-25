<?php
/**
 * @var string|null                     $activeNav
 * @var array{id: int, username: string} $admin
 */
$navigationItems = [
    'dashboard' => ['Dashboard', 'admin/dashboard.php'],
    'reservations' => ['Reservations', 'admin/reservations.php'],
    'schedule' => ['Daily Schedule', 'admin/schedule.php'],
    'payments' => ['Payments', 'admin/payments.php'],
    'time-slots' => ['Time Slots', 'admin/time-slots.php'],
];
?>
<nav class="admin-nav" aria-label="Admin">
    <ul class="admin-nav-links">
        <?php foreach ($navigationItems as $key => [$label, $path]): ?>
            <li>
                <a href="<?= e(url($path)) ?>"<?= $key === $activeNav ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <form class="admin-nav-signout" method="post" action="<?= e(url('admin/logout.php')) ?>">
        <?= csrf_field() ?>
        <span class="admin-nav-user" title="Signed in"><?= e($admin['username']) ?></span>
        <button type="submit" class="button button-small button-ghost">Sign out</button>
    </form>
</nav>
