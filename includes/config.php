<?php
/**
 * ATTENDANCE SYSTEM — Configuration & Database Connection
 */

// 1. ERROR REPORTING (Off for production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);


// 2. SESSION CONFIGURATION
if (getenv('VERCEL') || getenv('VERCEL_URL')) {
    define('ENVIRONMENT', 'cloud');
    session_save_path('/tmp');
} else {
    define('ENVIRONMENT', 'local');
}

// Modern Session Settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. TIMEZONE SETTINGS
date_default_timezone_set("Africa/Lagos");

// 4. DATABASE CREDENTIALS
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

// 5. DATABASE CONNECTION
$conn = mysqli_init();

if (ENVIRONMENT === 'cloud') {
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    $conn->real_connect($servername, $username, $password, $database, null, null, MYSQLI_CLIENT_SSL);
} else {
    $conn->real_connect($servername, $username, $password, $database);
}

if ($conn->connect_error) {
    die("Database Connection Error: " . $conn->connect_error);
}
