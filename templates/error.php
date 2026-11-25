<?php
/**
 * Standalone error page. Deliberately does not use the database or the
 * session, so it can be shown even when those are unavailable.
 *
 * @var int         $status
 * @var string      $message
 * @var string|null $details stack trace, only in development
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error <?= (int) $status ?> · Tennis Court Reservation</title>
    <link rel="icon" href="<?= e(asset_url('images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="area-public">
<main class="container page page-narrow">
    <section class="card">
        <p class="eyebrow">Error <?= (int) $status ?></p>
        <h1>The request could not be completed</h1>
        <p><?= e($message) ?></p>
        <?php if (!empty($details)): ?>
            <pre class="error-details"><?= e($details) ?></pre>
        <?php endif; ?>
        <p><a class="button button-secondary" href="<?= e(url('index.php')) ?>">Back to the booking page</a></p>
    </section>
</main>
</body>
</html>
