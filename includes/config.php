<?php
/**
 * ATTENDANCE SYSTEM — Configuration & Database Connection
 */

// 1. SESSION CONFIGURATION (Must be before session_start)
if (getenv('VERCEL') || getenv('VERCEL_URL')) {
    define('ENVIRONMENT', 'cloud');
    session_save_path('/tmp');
} else {
    define('ENVIRONMENT', 'local');
}

// Ensure cookies work across the whole domain
session_set_cookie_params([
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. TIMEZONE SETTINGS
date_default_timezone_set("Africa/Lagos");

// 3. DATABASE CREDENTIALS
if (ENVIRONMENT === 'cloud') {
    $servername = getenv('DB_HOST');
    $username   = getenv('DB_USER');
    $password   = getenv('DB_PASSWORD');
    $database   = getenv('DB_NAME');
} else {
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $database   = "attendance_system";
}

// 4. DATABASE CONNECTION
$conn = mysqli_init();

if (ENVIRONMENT === 'cloud') {
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    $conn->real_connect($servername, $username, $password, $database, null, null, MYSQLI_CLIENT_SSL);
} else {
    $conn->real_connect($servername, $username, $password, $database);
}

if ($conn->connect_error) {
    die("Connection failed.");
}
// No closing tag to avoid whitespace issues
