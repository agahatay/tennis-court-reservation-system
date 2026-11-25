<?php
/**
 * @var string      $title     page title
 * @var string      $area      "public" or "admin"
 * @var string|null $activeNav key of the active admin navigation item
 */
$area ??= 'public';
$activeNav ??= null;
$signedInAdmin = $area === 'admin' ? current_admin() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Tennis Court Reservation</title>
    <link rel="icon" href="<?= e(asset_url('images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
    <script src="<?= e(asset_url('js/app.js')) ?>" defer></script>
</head>
<body class="area-<?= e($area) ?>">
<header class="site-header">
    <div class="container site-header-inner">
        <a class="brand" href="<?= e(url($signedInAdmin ? 'admin/dashboard.php' : 'index.php')) ?>">
            <span class="brand-ball" aria-hidden="true"></span>
            <span>Tennis Court Reservation</span>
        </a>
        <?php if ($signedInAdmin !== null): ?>
            <?php render('admin-nav', ['activeNav' => $activeNav, 'admin' => $signedInAdmin]); ?>
        <?php endif; ?>
    </div>
</header>
<main class="container page">
    <?php render('flash-messages'); ?>
