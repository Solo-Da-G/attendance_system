<?php
// Suppress deprecations and notices so minor runtime deprecations in PHP 8.5+ do not break headers or UI output
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);

/**
 * Determine whether the current request is HTTPS.
 * (Vercel sits behind a proxy, so we also check X-Forwarded-Proto.)
 */
if (!function_exists('is_https_request')) {
    function is_https_request(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
        return false;
    }
}

// Start session (configured to persist until manual logout)
if (session_status() === PHP_SESSION_NONE) {
    $secure = is_https_request();
    // Keep session active for 30 days without timing out when idle
    @ini_set('session.gc_maxlifetime', 86400 * 30);
    @ini_set('session.cookie_lifetime', 86400 * 30);
    @session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
/**
 * ATTENDANCE SYSTEM — Configuration
 * 
 * This config auto-detects the environment:
 *   - LOCALHOST:  XAMPP on your own PC
 *   - LAN:       Office network (other devices connect via your PC's IP)
 *   - CLOUD:     Hosted on a web server (Hostinger, DigitalOcean, etc.)
 * 
 * HOW TO CONFIGURE FOR CLOUD HOSTING:
 *   1. Change ENVIRONMENT to 'cloud'
 *   2. Fill in your cloud database credentials below
 *   3. Set CLOUD_URL to your domain (e.g. https://attendance.yourcompany.com)
 *   4. Set API_SECRET to a random string for hybrid sync security
 */

// ================================================================
// DATABASE CREDENTIALS (Auto-detect environment)
// ================================================================
$envDbHost = getenv('DB_HOST') ?: getenv('HOST');
if ($envDbHost) {
    // ---- VERCEL / CLOUD ENVIRONMENT ----
    define('ENVIRONMENT', 'cloud');
    
    // Support both standard names (DB_HOST, DB_USER, etc.) and TiDB names (HOST, USERNAME, etc.)
    // If TiDB host was entered in HOST or DB_HOST, prioritize TiDB
    if (getenv('HOST') && stripos(getenv('HOST'), 'tidb') !== false) {
        $servername = getenv('HOST');
    } elseif (getenv('DB_HOST') && stripos(getenv('DB_HOST'), 'tidb') !== false) {
        $servername = getenv('DB_HOST');
    } else {
        $servername = getenv('DB_HOST') ?: getenv('HOST');
    }

    if (getenv('USERNAME') && stripos(getenv('USERNAME'), '.root') !== false) {
        $username = getenv('USERNAME');
    } elseif (getenv('DB_USER') && stripos(getenv('DB_USER'), '.root') !== false) {
        $username = getenv('DB_USER');
    } else {
        $username = getenv('DB_USER') ?: getenv('USERNAME');
    }

    // Password: if HOST is TiDB and PASSWORD is set, use it
    if (getenv('HOST') && stripos(getenv('HOST'), 'tidb') !== false && getenv('PASSWORD')) {
        $password = getenv('PASSWORD');
    } elseif (getenv('DB_HOST') && stripos(getenv('DB_HOST'), 'tidb') !== false && getenv('DB_PASSWORD')) {
        $password = getenv('DB_PASSWORD');
    } else {
        $password = getenv('DB_PASSWORD') ?: getenv('PASSWORD');
    }

    $database = getenv('DB_NAME') ?: (getenv('DATABASE') ?: 'test');
    $port     = (int)(getenv('DB_PORT') ?: (getenv('PORT') ?: 4000));
    
    // Clean hostname if port was included in servername
    if (str_contains($servername, ':')) {
        $parts = explode(':', $servername, 2);
        $servername = $parts[0];
        if (is_numeric($parts[1])) {
            $port = (int)$parts[1];
        }
    }

    // TiDB Cloud auto-correction: if old Aiven port (12323) or database (defaultdb) lingered in Vercel, enforce TiDB defaults
    if (stripos($servername, 'tidb') !== false) {
        if ($port === 12323 || $port === 3306 || empty($port)) {
            $port = 4000;
        }
        if ($database === 'defaultdb' || empty($database)) {
            $database = 'test';
        }
    }
} else {
    // ---- LOCAL / LAN (XAMPP defaults) ----
    define('ENVIRONMENT', 'local');
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $database   = "attendance_system";
    $port       = 3306;
}

// ================================================================
// APP SETTINGS
// ================================================================

// Your cloud URL (used by hybrid sync to push data from local to cloud)
define('CLOUD_URL', 'https://attendance.yourcompany.com');

// API secret key for secure communication between local sync and cloud
define('API_SECRET', getenv('API_SECRET') ?: 'CHANGE_THIS_TO_A_RANDOM_STRING_123');

// Timezone
date_default_timezone_set("Africa/Lagos");

// ================================================================
// DATABASE CONNECTION
// ================================================================
// Suppress unhandled driver exceptions and handle connection retries gracefully
mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_init();
if (!$conn) {
    die("Database initialization failed.");
}

if (ENVIRONMENT === 'cloud') {
    // Enable SSL for cloud database services (TiDB Cloud, Aiven, etc.)
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    
    // Resilient serverless connection retry (up to 3 attempts with 200ms backoff)
    $connected = false;
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        try {
            $connected = @$conn->real_connect($servername, $username, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL);
            if ($connected && !$conn->connect_error) {
                break;
            }
        } catch (Throwable $t) {
            $connected = false;
        }
        if ($attempt < 3) {
            usleep(200000); // 200ms backoff
        }
    }
} else {
    @$conn->real_connect($servername, $username, $password, $database, $port);
}

if ($conn->connect_error) {
    if (ENVIRONMENT === 'cloud') {
        die("Database connection failed. Please check your hosting credentials: " . $conn->connect_error);
    } else {
        die("Database connection failed: " . $conn->connect_error);
    }
}

$conn->set_charset("utf8mb4");

if (!function_exists('db_table_columns')) {
    function db_table_columns(mysqli $conn, string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $columns = [];
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $result = $conn->query("SHOW COLUMNS FROM `{$tableSafe}`");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }

        $cache[$table] = $columns;
        return $columns;
    }
}

if (!function_exists('db_has_column')) {
    function db_has_column(mysqli $conn, string $table, string $column): bool
    {
        return in_array($column, db_table_columns($conn, $table), true);
    }
}

if (!function_exists('db_has_table')) {
    function db_has_table(mysqli $conn, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $result = $conn->query("SHOW TABLES LIKE '{$tableSafe}'");
        $cache[$table] = (bool)($result && $result->num_rows > 0);
        return $cache[$table];
    }
}

// Auto-heal schema for password change requirements, cards, and WFH (TiDB & MySQL safe)
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    try {
        if (db_has_table($conn, 'staff') && !db_has_column($conn, 'staff', 'must_change_password')) {
            $conn->query("ALTER TABLE `staff` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (db_has_table($conn, 'admin') && !db_has_column($conn, 'admin', 'must_change_password')) {
            $conn->query("ALTER TABLE `admin` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (db_has_table($conn, 'staff') && !db_has_column($conn, 'staff', 'card_uid')) {
            // TiDB safe: add column first, then optional unique index
            $conn->query("ALTER TABLE `staff` ADD COLUMN `card_uid` VARCHAR(100) NULL AFTER `fingerprint_id`");
            try {
                $conn->query("ALTER TABLE `staff` ADD UNIQUE INDEX `uq_card_uid` (`card_uid`)");
            } catch (Throwable $t) {}
        }

        // ── Auto-heal WFH system settings table ──
        if (!db_has_table($conn, 'system_settings')) {
            $conn->query("CREATE TABLE IF NOT EXISTS `system_settings` (
                `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
                `setting_value` TEXT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Seed defaults: Tuesdays & Thursdays enabled
            $conn->query("INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES 
                ('wfh_days', 'Tuesday,Thursday'),
                ('wfh_task_required', '0')");
        }

        // ── Auto-heal attendance table for Work From Home tracking ──
        if (db_has_table($conn, 'attendance')) {
            if (!db_has_column($conn, 'attendance', 'work_mode')) {
                $conn->query("ALTER TABLE `attendance` ADD COLUMN `work_mode` VARCHAR(20) NOT NULL DEFAULT 'office' AFTER `status`");
            }
            if (!db_has_column($conn, 'attendance', 'wfh_task')) {
                $conn->query("ALTER TABLE `attendance` ADD COLUMN `wfh_task` TEXT NULL AFTER `work_mode`");
            }
            if (!db_has_column($conn, 'attendance', 'wfh_address_in')) {
                $conn->query("ALTER TABLE `attendance` ADD COLUMN `wfh_address_in` VARCHAR(255) NULL AFTER `lng_in`");
            }
            if (!db_has_column($conn, 'attendance', 'wfh_address_out')) {
                $conn->query("ALTER TABLE `attendance` ADD COLUMN `wfh_address_out` VARCHAR(255) NULL AFTER `lng_out`");
            }
        }
    } catch (Throwable $t) {
        // Suppress schema auto-heal errors so they never interrupt normal page loading
    }
}

// ── WFH & System Settings Helper Functions ──
if (!function_exists('get_system_setting')) {
    function get_system_setting(mysqli $conn, string $key, string $default = ''): string {
        if (!isset($GLOBALS['__system_setting_cache'])) {
            $GLOBALS['__system_setting_cache'] = [];
        }
        if (isset($GLOBALS['__system_setting_cache'][$key])) {
            return $GLOBALS['__system_setting_cache'][$key];
        }
        try {
            $stmt = $conn->prepare("SELECT setting_value FROM `system_settings` WHERE setting_key = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $key);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $row = $res->fetch_assoc()) {
                    $stmt->close();
                    $val = (string)($row['setting_value'] ?? '');
                    $GLOBALS['__system_setting_cache'][$key] = $val;
                    return $val;
                }
                $stmt->close();
            }
        } catch (Throwable $e) {}
        return $default;
    }
}

if (!function_exists('set_system_setting')) {
    function set_system_setting(mysqli $conn, string $key, string $value): bool {
        try {
            $stmt = $conn->prepare("INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)");
            if ($stmt) {
                $stmt->bind_param("ss", $key, $value);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) {
                    if (!isset($GLOBALS['__system_setting_cache'])) {
                        $GLOBALS['__system_setting_cache'] = [];
                    }
                    $GLOBALS['__system_setting_cache'][$key] = $value;
                }
                return $ok;
            }
        } catch (Throwable $e) {}
        return false;
    }
}

if (!function_exists('get_wfh_days')) {
    function get_wfh_days(mysqli $conn): array {
        $raw = get_system_setting($conn, 'wfh_days', 'Tuesday,Thursday');
        if (trim($raw) === '' || strtolower(trim($raw)) === 'none') return [];
        $parts = array_map('trim', explode(',', $raw));
        return array_values(array_filter($parts, function($d) {
            return !empty($d) && strtolower($d) !== 'none';
        }));
    }
}

if (!function_exists('is_wfh_day_today')) {
    function is_wfh_day_today(mysqli $conn): bool {
        $wfh_days = get_wfh_days($conn);
        if (empty($wfh_days)) return false;
        $current_day = date('l'); // e.g. "Tuesday"
        foreach ($wfh_days as $day) {
            if (strcasecmp($day, $current_day) === 0) {
                return true;
            }
        }
        return false;
    }
}

// ================================================================
// SECURITY HEADERS (for cloud)
// ================================================================
if (ENVIRONMENT === 'cloud') {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
// ================================================================
// VERCEL STATELESS SESSION RESTORE
// ================================================================
if (empty($_SESSION['admin_id']) && empty($_SESSION['staff_id']) && isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
    
    if (str_starts_with($token, 'staff_')) {
        $real_token = substr($token, 6);
        $stmt = $conn->prepare("SELECT id, staff_id, full_name FROM `staff` WHERE auth_token = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $real_token);
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

require_once __DIR__ . '/auto_detect_branch.php';
?>
