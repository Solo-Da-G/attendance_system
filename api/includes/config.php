<?php
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

// Start session (with sane cookie settings for both local + HTTPS deployments)
if (session_status() === PHP_SESSION_NONE) {
    $secure = is_https_request();
    // Ensure the session cookie works correctly on HTTPS and doesn't break local HTTP.
    // Note: must be called BEFORE session_start()
    @session_set_cookie_params([
        'lifetime' => 0,
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
if (getenv('DB_HOST')) {
    // ---- VERCEL / CLOUD ENVIRONMENT ----
    define('ENVIRONMENT', 'cloud');
    $servername = getenv('DB_HOST');
    $username   = getenv('DB_USER');
    $password   = getenv('DB_PASSWORD');
    $database   = getenv('DB_NAME');
    $port       = getenv('DB_PORT') ?: 3306;
    
    // Adjust servername for port if needed
    if ($port != 3306 && !str_contains($servername, ':')) {
        $servername .= ":" . $port;
    }
} else {
    // ---- LOCAL / LAN (XAMPP defaults) ----
    define('ENVIRONMENT', 'local');
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $database   = "attendance_system";
}

// ================================================================
// APP SETTINGS
// ================================================================

// Your cloud URL (used by hybrid sync to push data from local to cloud)
define('CLOUD_URL', 'https://attendance.yourcompany.com');

// API secret key for secure communication between local sync and cloud
define('API_SECRET', 'CHANGE_THIS_TO_A_RANDOM_STRING_123');

// Timezone
date_default_timezone_set("Africa/Lagos");

// ================================================================
// DATABASE CONNECTION
// ================================================================
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    if (ENVIRONMENT === 'cloud') {
        die("Database connection failed. Please check your hosting credentials.");
    } else {
        die("Database connection failed: " . $conn->connect_error);
    }
}

$conn->set_charset("utf8mb4");


// Add after connection
if ($conn->connect_error) {
    error_log("DB Connection Failed: " . $conn->connect_error);
    die("Database connection failed. Please check your environment variables.");
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
?>
