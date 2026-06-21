<?php

$host = getenv('ICSS_DB_HOST') ?: 'localhost';
$user = getenv('ICSS_DB_USER') ?: 'root';
$pass = getenv('ICSS_DB_PASS') ?: '';
$dbname = getenv('ICSS_DB_NAME') ?: 'icss';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    die("Database connection failed.");
}

$conn->set_charset('utf8mb4');

require_once __DIR__ . '/../includes/global_theme.php';
startGlobalTheme();

?>
