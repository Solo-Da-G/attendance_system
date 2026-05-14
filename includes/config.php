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
if (ENVIRONMENT === 'cloud') {
    session_save_path(sys_get_temp_dir());
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
    if ($col && !is_bool($col) && $col->num_rows === 0) {
        $conn->query("ALTER TABLE admin ADD COLUMN auth_token VARCHAR(64) DEFAULT NULL");
    }

    // Ensure staff photo column can hold Base64 data
    $photo_col = $conn->query("SHOW COLUMNS FROM staff LIKE 'photo'");
    if ($photo_col && $row_col = $photo_col->fetch_assoc()) {
        if (stripos($row_col['Type'], 'mediumtext') === false && stripos($row_col['Type'], 'longtext') === false) {
            $conn->query("ALTER TABLE staff MODIFY COLUMN photo MEDIUMTEXT DEFAULT NULL");
        }
    }

    // Ensure staff password column exists for logins
    $pass_col = $conn->query("SHOW COLUMNS FROM staff LIKE 'password'");
    if ($pass_col && $pass_col->num_rows === 0) {
        $conn->query("ALTER TABLE staff ADD COLUMN password VARCHAR(255) DEFAULT NULL");
    }

    // Ensure reset_token columns exist
    $res_adm = $conn->query("SHOW COLUMNS FROM admin LIKE 'reset_token'");
    if ($res_adm && !is_bool($res_adm) && $res_adm->num_rows === 0) {
        $conn->query("ALTER TABLE admin ADD COLUMN reset_token VARCHAR(100) DEFAULT NULL");
    }
    $res_stf = $conn->query("SHOW COLUMNS FROM staff LIKE 'reset_token'");
    if ($res_stf && !is_bool($res_stf) && $res_stf->num_rows === 0) {
        $conn->query("ALTER TABLE staff ADD COLUMN reset_token VARCHAR(100) DEFAULT NULL");
    }

    // Ensure attendance photo columns exist
    $att_p_in = $conn->query("SHOW COLUMNS FROM attendance LIKE 'photo_in'");
    if ($att_p_in && !is_bool($att_p_in) && $att_p_in->num_rows === 0) {
        $conn->query("ALTER TABLE attendance ADD COLUMN photo_in MEDIUMTEXT DEFAULT NULL");
    }
    $att_p_out = $conn->query("SHOW COLUMNS FROM attendance LIKE 'photo_out'");
    if ($att_p_out && !is_bool($att_p_out) && $att_p_out->num_rows === 0) {
        $conn->query("ALTER TABLE attendance ADD COLUMN photo_out MEDIUMTEXT DEFAULT NULL");
    }

    // Ensure branch geofencing columns exist (check if table exists first)
    $chk_br = $conn->query("SHOW TABLES LIKE 'branches'");
    if ($chk_br && $chk_br->num_rows > 0) {
        $br_lat = $conn->query("SHOW COLUMNS FROM branches LIKE 'latitude'");
        if ($br_lat && !is_bool($br_lat) && $br_lat->num_rows === 0) {
            $conn->query("ALTER TABLE branches ADD COLUMN latitude DECIMAL(10,8) DEFAULT 6.5244");
            $conn->query("ALTER TABLE branches ADD COLUMN longitude DECIMAL(11,8) DEFAULT 3.3792");
            $conn->query("ALTER TABLE branches ADD COLUMN radius_meters INT DEFAULT 200");
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
