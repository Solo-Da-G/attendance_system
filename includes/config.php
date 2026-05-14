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

// 4. API & SYNC CONFIG
define('API_SECRET', 'Attendance_Secret_Key_2026'); // Change this to a secure random string
define('CLOUD_URL', 'https://attendance-system-delta-five.vercel.app'); 

// 5. DATABASE CREDENTIALS
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
try {
    $conn = mysqli_init();
    if (!$conn) {
        die("mysqli_init failed");
    }

    if (ENVIRONMENT === 'cloud') {
        $db_host_raw = getenv('DB_HOST');
        $db_port     = 3306;
        if (strpos($db_host_raw, ':') !== false) {
            list($db_host_clean, $db_port_str) = explode(':', $db_host_raw, 2);
            $db_port = (int)$db_port_str;
        } else {
            $db_host_clean = $db_host_raw;
        }

        // Try with SSL first, fallback to normal if it fails
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $connected = @$conn->real_connect($db_host_clean, $username, $password, $database, $db_port, null, MYSQLI_CLIENT_SSL);
        
        if (!$connected) {
            // Fallback to non-SSL
            $connected = @$conn->real_connect($db_host_clean, $username, $password, $database, $db_port);
        }
    } else {
        $connected = @$conn->real_connect($servername, $username, $password, $database);
    }

    if (!$connected) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    if (isset($_GET['debug'])) {
        die("Connection Error: " . $e->getMessage());
    } else {
        die("Database connection failed. Please check environment variables.");
    }
}

// 6. SESSION SETUP
// Note: On Vercel, sessions are ephemeral. We rely on the auth_token cookie to restore state.
ini_set('session.cookie_path', '/');
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 7. DB SCHEMA AUTO-MIGRATION
// To prevent 500 errors and slow-downs, we only check this if needed.
if (empty($_SESSION['schema_checked'])) {
    // Wrap in try-catch to prevent site-wide crash if a query fails
    try {
        // Ensure auth_token column exists on admin table
        $conn->query("ALTER TABLE `admin` ADD COLUMN IF NOT EXISTS `auth_token` VARCHAR(64) DEFAULT NULL");
        
        // Ensure staff columns
        $conn->query("ALTER TABLE `staff` ADD COLUMN IF NOT EXISTS `password` VARCHAR(255) DEFAULT NULL");
        $conn->query("ALTER TABLE `staff` ADD COLUMN IF NOT EXISTS `reset_token` VARCHAR(100) DEFAULT NULL");
        
        // Ensure attendance columns
        $conn->query("ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `photo_in` MEDIUMTEXT DEFAULT NULL");
        $conn->query("ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `photo_out` MEDIUMTEXT DEFAULT NULL");

        // Ensure branches geofencing
        $chk_br = $conn->query("SHOW TABLES LIKE 'branches'");
        if ($chk_br && $chk_br->num_rows > 0) {
            $conn->query("ALTER TABLE `branches` ADD COLUMN IF NOT EXISTS `latitude` DECIMAL(10,8) DEFAULT 6.5244");
            $conn->query("ALTER TABLE `branches` ADD COLUMN IF NOT EXISTS `longitude` DECIMAL(11,8) DEFAULT 3.3792");
            $conn->query("ALTER TABLE `branches` ADD COLUMN IF NOT EXISTS `radius_meters` INT DEFAULT 200");
        }
        
        $_SESSION['schema_checked'] = true;
    } catch (Exception $e) {
        // Log error but don't crash the site
        error_log("Migration Error: " . $e->getMessage());
    }
}

// 8. COOKIE-BASED AUTH RESTORE
// This is CRITICAL for Vercel persistence
if (empty($_SESSION['admin_id']) && !empty($_COOKIE['auth_token'])) {
    $token = preg_replace('/[^a-zA-Z0-9]/', '', $_COOKIE['auth_token']);
    if (strlen($token) >= 32) { // Allow for different token lengths
        $stmt = $conn->prepare("SELECT id, username, role FROM `admin` WHERE auth_token = ? LIMIT 1");
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
