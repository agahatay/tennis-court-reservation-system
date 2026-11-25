<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

if (current_admin() !== null) {
    redirect('admin/dashboard.php');
}

$lockoutMessage = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf_token();

    $username = request_string($_POST, 'username', 50);
    $password = $_POST['password'] ?? '';
    $ipAddress = client_ip_address();
    $now = now();

    $secondsLocked = login_lockout_seconds_remaining($ipAddress, $username, $now);
    if ($secondsLocked > 0) {
        // Refused without checking the password, so guessing is impossible while locked.
        http_response_code(429);
        header('Retry-After: ' . $secondsLocked);
        $minutes = (int) ceil($secondsLocked / 60);
        $lockoutMessage = sprintf(
            'Too many failed sign-in attempts. Please try again in %d %s.',
            $minutes,
            $minutes === 1 ? 'minute' : 'minutes'
        );
    } elseif (is_string($password) && attempt_admin_login($username, $password)) {
        clear_failed_logins($ipAddress, $username);
        redirect('admin/dashboard.php');
    } else {
        record_failed_login($ipAddress, $username, $now);
        delete_login_attempts_before($now->modify('-1 day'));

        // Same message for unknown users and wrong passwords.
        remember_form_state(['username' => $username], []);
        flash('error', 'Invalid username or password.');
        redirect('admin/login.php');
    }
}

['old' => $oldInput] = pull_form_state();
if ($lockoutMessage !== null) {
    $oldInput['username'] = $username;
}
$noAdminAccountExists = count_admins() === 0;

render('header', ['title' => 'Admin Sign In', 'area' => 'admin']);
?>
<section class="card auth-card">
    <header class="card-header">
        <h1>Admin sign in</h1>
        <p class="muted">Manage reservations, schedules and payments.</p>
    </header>

    <?php if ($lockoutMessage !== null): ?>
        <div class="alert alert-error" role="alert"><?= e($lockoutMessage) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['signed_out'])): ?>
        <div class="alert alert-success" role="status">You have been signed out.</div>
    <?php endif; ?>

    <?php if ($noAdminAccountExists): ?>
        <div class="alert alert-info">
            No administrator account exists yet. Create one from the project folder with
            <code>php scripts/create-admin.php &lt;username&gt;</code>.
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/login.php')) ?>" class="stack">
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" autocomplete="username" maxlength="50" required autofocus
                   value="<?= e($oldInput['username'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="button button-primary button-block">Sign in</button>
    </form>
</section>
<?php render('footer'); ?>
