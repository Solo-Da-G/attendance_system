<?php
/**
 * ATTENDANCE SYSTEM — Configuration
 */

// 1. ERROR REPORTING
ini_set('display_errors', 0);
error_reporting(0);

// 2. ENVIRONMENT DETECTION
if (getenv('VERCEL') || getenv('VERCEL_URL')) {
    define('ENVIRONMENT', 'cloud');
} else {
    define('ENVIRONMENT', 'local');
}

// 3. TIMEZONE
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

// 5. DATABASE CONNECTION (SSL for Aiven)
$conn = mysqli_init();
if (ENVIRONMENT === 'cloud') {
    // DB_HOST may contain port as "hostname:port" — split them
    $db_host_raw = getenv('DB_HOST');
    $db_port     = 3306; // default MySQL port
    if (strpos($db_host_raw, ':') !== false) {
        list($db_host_clean, $db_port_str) = explode(':', $db_host_raw, 2);
        $db_port = (int)$db_port_str;
    } else {
        $db_host_clean = $db_host_raw;
    }
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    $conn->real_connect($db_host_clean, $username, $password, $database, $db_port, null, MYSQLI_CLIENT_SSL);
} else {
    $conn->real_connect($servername, $username, $password, $database);
}

if ($conn->connect_error) {
    die("Database connection failed.");
}

// 6. SESSION SETUP
if (ENVIRONMENT === 'cloud') {
    session_save_path('/tmp');
}
ini_set('session.cookie_path', '/');
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 7. ONE-TIME DB SCHEMA CHECKS (only runs once per session to avoid slowdowns)
if (empty($_SESSION['schema_checked'])) {
    // Ensure auth_token column exists on admin table
    $col = $conn->query("SHOW COLUMNS FROM admin LIKE 'auth_token'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE admin ADD COLUMN auth_token VARCHAR(64) DEFAULT NULL");
    }

    // Ensure staff photo column can hold Base64 data
    $photo_col = $conn->query("SHOW COLUMNS FROM staff LIKE 'photo'");
    if ($photo_col && $row_col = $photo_col->fetch_assoc()) {
        if (stripos($row_col['Type'], 'mediumtext') === false && stripos($row_col['Type'], 'longtext') === false) {
            $conn->query("ALTER TABLE staff MODIFY COLUMN photo MEDIUMTEXT DEFAULT NULL");
        }
    }

    $_SESSION['schema_checked'] = true;
}

// 8. COOKIE-BASED AUTH RESTORE
// If session lost (new Vercel instance), restore from auth cookie stored in DB
if (empty($_SESSION['admin_id']) && !empty($_COOKIE['auth_token'])) {
    $token = preg_replace('/[^a-zA-Z0-9]/', '', $_COOKIE['auth_token']);
    if (strlen($token) === 64) {
        $stmt = $conn->prepare("SELECT id, username, role FROM admin WHERE auth_token = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $row = $res->fetch_assoc();
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin']    = $row['username'];
                $_SESSION['role']     = $row['role'];
            }
            $stmt->close();
        }
    }
}
?>
