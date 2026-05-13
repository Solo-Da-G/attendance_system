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
function get_db_connection() {
    global $servername, $username, $password, $database;
    static $conn = null;
    if ($conn === null || !mysqli_ping($conn)) {
        $conn = mysqli_init();
        if (ENVIRONMENT === 'cloud') {
            $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
            $conn->real_connect($servername, $username, $password, $database, null, null, MYSQLI_CLIENT_SSL);
        } else {
            $conn->real_connect($servername, $username, $password, $database);
        }
    }
    return $conn;
}

// Initial connection
$conn = get_db_connection();
if (!$conn) { die("Database Connection Failed."); }

// 6. BULLETPROOF DATABASE SESSIONS
function sess_open($path, $name) { return true; }
function sess_close() { return true; }
function sess_read($id) {
    $db = get_db_connection();
    $stmt = mysqli_prepare($db, "SELECT data FROM sessions WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "s", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) { return (string)$row['data']; }
    return "";
}
function sess_write($id, $data) {
    $db = get_db_connection();
    $ts = time();
    $stmt = mysqli_prepare($db, "REPLACE INTO sessions (id, data, timestamp) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssi", $id, $data, $ts);
    return mysqli_stmt_execute($stmt);
}
function sess_destroy($id) {
    $db = get_db_connection();
    $stmt = mysqli_prepare($db, "DELETE FROM sessions WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "s", $id);
    return mysqli_stmt_execute($stmt);
}
function sess_gc($max) {
    $db = get_db_connection();
    $ts = time() - $max;
    mysqli_query($db, "DELETE FROM sessions WHERE timestamp < $ts");
    return true;
}

// Register handler
session_set_save_handler("sess_open", "sess_close", "sess_read", "sess_write", "sess_destroy", "sess_gc");

// Ensure session is written BEFORE shutdown to avoid crashes
register_shutdown_function('session_write_close');

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
