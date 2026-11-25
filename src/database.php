<?php
// Transitional connection file: credentials now come from configuration (.env)
// instead of being hardcoded. Replaced by a full data-access layer in a later step.
require_once __DIR__ . '/config.php';

$dbConfig = app_config()['db'];
$conn = mysqli_connect($dbConfig['host'], $dbConfig['user'], $dbConfig['password'], $dbConfig['name'], $dbConfig['port']);
mysqli_set_charset($conn, 'utf8mb4');
