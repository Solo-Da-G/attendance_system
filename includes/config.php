<?php
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
// ENVIRONMENT: 'local' | 'lan' | 'cloud'
// ================================================================
// Auto-detect Vercel environment
if (getenv('VERCEL') || getenv('VERCEL_URL')) {
    define('ENVIRONMENT', 'cloud');
} else {
    define('ENVIRONMENT', 'local');
}

// ================================================================
// DATABASE CREDENTIALS (per environment)
// ================================================================
if (ENVIRONMENT === 'cloud') {
    // ---- CLOUD HOSTING (using Vercel Environment Variables) ----
    $servername = getenv('DB_HOST') ?: "localhost";
    $username   = getenv('DB_USER') ?: "your_db_username";
    $password   = getenv('DB_PASSWORD') ?: "your_db_password";
    $database   = getenv('DB_NAME') ?: "your_db_name";
} else {
    // ---- LOCAL / LAN (XAMPP defaults) ----
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
$conn = mysqli_init();

if (ENVIRONMENT === 'cloud') {
    // Aiven requires SSL. We enable SSL but skip strict cert verification for ease of setup
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    $conn->real_connect($servername, $username, $password, $database, null, null, MYSQLI_CLIENT_SSL);
} else {

    $conn->real_connect($servername, $username, $password, $database);
}

if ($conn->connect_error) {

    if (ENVIRONMENT === 'cloud') {
        die("Database connection failed. Please check your hosting credentials.");
    } else {
        die("Database connection failed: " . $conn->connect_error);
    }
}

$conn->set_charset("utf8mb4");

// ================================================================
// SECURITY HEADERS (for cloud)
// ================================================================
if (ENVIRONMENT === 'cloud') {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}
?>
