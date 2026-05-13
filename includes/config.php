<?php
/**
 * ATTENDANCE SYSTEM — Configuration
 */

// 1. ERROR REPORTING (Off for production)
ini_set('display_errors', 0);
error_reporting(0);

// 2. ENVIRONMENT DETECTION
if (getenv('VERCEL') || getenv('VERCEL_URL')) {
    define('ENVIRONMENT', 'cloud');
} else {
    define('ENVIRONMENT', 'local');
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

// 5. DATABASE CONNECTION (with SSL for Aiven)
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

// 6. SESSION — Use /tmp on Vercel
if (ENVIRONMENT === 'cloud') {
    session_save_path('/tmp');
}
ini_set('session.cookie_path', '/');
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 7. COOKIE-BASED AUTH RESTORE
// If session is empty but auth cookie exists, restore session from DB
if (empty($_SESSION['admin_id']) && isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
    $token = preg_replace('/[^a-zA-Z0-9]/', '', $token); // sanitize
    $result = $conn->query("SELECT id, username, role FROM admin WHERE auth_token = '$token' LIMIT 1");
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin']    = $row['username'];
        $_SESSION['role']     = $row['role'];
    }
}
?>
