<?php
/**
 * ATTENDANCE SYSTEM — Configuration
 */

// 1. ERROR REPORTING & HANDLING
ini_set('display_errors', 1);
error_reporting(E_ALL);

set_exception_handler(function($e) {
    echo "<div style='padding:40px; background:#fee2e2; color:#991b1b; font-family:sans-serif;'>";
    echo "<h2>Fatal Application Error</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " on line " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
    exit;
});

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
mysqli_report(MYSQLI_REPORT_OFF); // Prevent PHP 8.1+ from throwing fatal exceptions on query errors
try {
    $conn = mysqli_init();
    if (!$conn) {
        die("mysqli_init failed");
    }

    if (ENVIRONMENT === 'cloud') {
        $db_host_raw = getenv('DB_HOST');
        $db_port     = getenv('DB_PORT') ?: 3306;
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
    die("Database Connection Error: " . $e->getMessage() . " | Host: " . getenv('DB_HOST') . " | User: " . getenv('DB_USER'));
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
    try {
        // Ensure auth_token & email columns exist on admin table
        $col = $conn->query("SHOW COLUMNS FROM `admin` LIKE 'auth_token'");
        if ($col && $col->num_rows === 0) $conn->query("ALTER TABLE `admin` ADD COLUMN `auth_token` VARCHAR(64) DEFAULT NULL");
        
        $email_col = $conn->query("SHOW COLUMNS FROM `admin` LIKE 'email'");
        if ($email_col && $email_col->num_rows === 0) $conn->query("ALTER TABLE `admin` ADD COLUMN `email` VARCHAR(150) DEFAULT NULL");
        
        // Ensure staff columns
        $pass_col = $conn->query("SHOW COLUMNS FROM `staff` LIKE 'password'");
        if ($pass_col && $pass_col->num_rows === 0) $conn->query("ALTER TABLE `staff` ADD COLUMN `password` VARCHAR(255) DEFAULT NULL");
        
        $stf_token = $conn->query("SHOW COLUMNS FROM `staff` LIKE 'auth_token'");
        if ($stf_token && $stf_token->num_rows === 0) $conn->query("ALTER TABLE `staff` ADD COLUMN `auth_token` VARCHAR(64) DEFAULT NULL");
        
        $res_stf = $conn->query("SHOW COLUMNS FROM `staff` LIKE 'reset_token'");
        if ($res_stf && $res_stf->num_rows === 0) $conn->query("ALTER TABLE `staff` ADD COLUMN `reset_token` VARCHAR(100) DEFAULT NULL");
        
        // Ensure attendance columns
        $att_in = $conn->query("SHOW COLUMNS FROM `attendance` LIKE 'photo_in'");
        if ($att_in && $att_in->num_rows === 0) $conn->query("ALTER TABLE `attendance` ADD COLUMN `photo_in` MEDIUMTEXT DEFAULT NULL");
        
        $att_out = $conn->query("SHOW COLUMNS FROM `attendance` LIKE 'photo_out'");
        if ($att_out && $att_out->num_rows === 0) $conn->query("ALTER TABLE `attendance` ADD COLUMN `photo_out` MEDIUMTEXT DEFAULT NULL");

        $att_lat = $conn->query("SHOW COLUMNS FROM `attendance` LIKE 'lat_in'");
        if ($att_lat && $att_lat->num_rows === 0) {
            $conn->query("ALTER TABLE `attendance` ADD COLUMN `lat_in` DECIMAL(10,8) NULL");
            $conn->query("ALTER TABLE `attendance` ADD COLUMN `lng_in` DECIMAL(11,8) NULL");
            $conn->query("ALTER TABLE `attendance` ADD COLUMN `lat_out` DECIMAL(10,8) NULL");
            $conn->query("ALTER TABLE `attendance` ADD COLUMN `lng_out` DECIMAL(11,8) NULL");
            $conn->query("ALTER TABLE `attendance` ADD COLUMN `is_geofenced` TINYINT(1) DEFAULT 0");
        }

        // staff.photo must hold base64 portraits
        $stf_photo = $conn->query("SHOW COLUMNS FROM `staff` LIKE 'photo'");
        if ($stf_photo && $stf_photo->num_rows > 0) {
            $col = $stf_photo->fetch_assoc();
            if (stripos($col['Type'] ?? '', 'varchar') !== false) {
                $conn->query("ALTER TABLE `staff` MODIFY COLUMN `photo` MEDIUMTEXT DEFAULT NULL");
            }
        }

        // Ensure branches geofencing
        $chk_br = $conn->query("SHOW TABLES LIKE 'branches'");
        if ($chk_br && $chk_br->num_rows > 0) {
            $br_lat = $conn->query("SHOW COLUMNS FROM `branches` LIKE 'latitude'");
            if ($br_lat && $br_lat->num_rows === 0) {
                $conn->query("ALTER TABLE `branches` ADD COLUMN `latitude` DECIMAL(10,8) DEFAULT 6.5244");
                $conn->query("ALTER TABLE `branches` ADD COLUMN `longitude` DECIMAL(11,8) DEFAULT 3.3792");
                $conn->query("ALTER TABLE `branches` ADD COLUMN `radius_meters` INT DEFAULT 200");
            }
        }
        
        $_SESSION['schema_checked'] = true;
    } catch (Exception $e) {
        error_log("Migration Error: " . $e->getMessage());
    }
}

// 8. COOKIE-BASED AUTH RESTORE
// This is CRITICAL for Vercel persistence
if (empty($_SESSION['admin_id']) && empty($_SESSION['staff_id']) && !empty($_COOKIE['auth_token'])) {
    $raw_token = $_COOKIE['auth_token'];
    $is_staff = strpos($raw_token, 'staff_') === 0;
    $token = preg_replace('/[^a-zA-Z0-9]/', '', str_replace('staff_', '', $raw_token));
    
    if (strlen($token) >= 32) {
        if ($is_staff) {
            $stmt = $conn->prepare("SELECT id, staff_id, full_name FROM `staff` WHERE auth_token = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows === 1) {
                    $row = $res->fetch_assoc();
                    $_SESSION['staff_id'] = $row['staff_id'];
                    $_SESSION['admin']    = $row['full_name'];
                    $_SESSION['role']     = 'staff';
                }
                $stmt->close();
            }
        } else {
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
}

// 9. FORCED PASSWORD CHANGE CHECK
if (!empty($_SESSION['staff_id']) && !empty($_SESSION['require_password_change'])) {
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page !== 'change_staff_password.php' && $current_page !== 'logout.php') {
        header("Location: change_staff_password.php");
        exit;
    }
}
?>
