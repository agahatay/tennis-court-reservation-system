<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

// Signing out is a state change, so it requires POST + CSRF token
// (a GET link could be triggered by another site).
require_post();
verify_csrf_token();

logout_admin();
redirect('admin/login.php', ['signed_out' => '1']);
