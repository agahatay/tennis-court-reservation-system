<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

redirect(current_admin() === null ? 'admin/login.php' : 'admin/dashboard.php');
