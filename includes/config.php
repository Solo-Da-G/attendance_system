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

// 5. DATABASE CONNECTION (Restoring SSL for Aiven)
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

// 6. DATABASE SESSIONS
function sess_open($path, $name) { return true; }
function sess_close() { return true; }
function sess_read($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT data FROM sessions WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { return (string)$row['data']; }
    return "";
}
function sess_write($id, $data) {
    global $conn;
    $ts = time();
    $stmt = $conn->prepare("REPLACE INTO sessions (id, data, timestamp) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $id, $data, $ts);
    return $stmt->execute();
}
function sess_destroy($id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM sessions WHERE id = ?");
    $stmt->bind_param("s", $id);
    return $stmt->execute();
}
function sess_gc($max) {
    global $conn;
    $ts = time() - $max;
    $conn->query("DELETE FROM sessions WHERE timestamp < $ts");
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
