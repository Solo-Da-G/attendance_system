<?php
/**
 * ATTENDANCE SYSTEM — Configuration
 */

// 1. ERROR REPORTING (Off for production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
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

// 5. DATABASE CONNECTION
$conn = mysqli_connect($servername, $username, $password, $database);
if (!$conn) { die("Connection failed."); }

// 6. DATABASE SESSIONS (Procedural version for max compatibility)
function sess_open($path, $name) { return true; }
function sess_close() { return true; }
function sess_read($id) {
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT data FROM sessions WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "s", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) { return (string)$row['data']; }
    return "";
}
function sess_write($id, $data) {
    global $conn;
    $ts = time();
    $stmt = mysqli_prepare($conn, "REPLACE INTO sessions (id, data, timestamp) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssi", $id, $data, $ts);
    return mysqli_stmt_execute($stmt);
}
function sess_destroy($id) {
    global $conn;
    $stmt = mysqli_prepare($conn, "DELETE FROM sessions WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "s", $id);
    return mysqli_stmt_execute($stmt);
}
function sess_gc($max) {
    global $conn;
    $ts = time() - $max;
    mysqli_query($conn, "DELETE FROM sessions WHERE timestamp < $ts");
    return true;
}

session_set_save_handler("sess_open", "sess_close", "sess_read", "sess_write", "sess_destroy", "sess_gc");

// Modern Session Settings
session_set_cookie_params([
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
