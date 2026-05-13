<?php
/**
 * ATTENDANCE SYSTEM — Configuration & Database Connection
 * 
 * This file handles:
 * 1. Timezone settings
 * 2. Environment detection (Local vs Cloud)
 * 3. Session initialization (Vercel compatible)
 * 4. Database connection using Environment Variables (Vercel) or static (Local)
 */

// 1. SESSION CONFIGURATION (Must be before session_start)
if (getenv('VERCEL') || getenv('VERCEL_URL')) {
    define('ENVIRONMENT', 'cloud');
    // Vercel only allows writing to /tmp
    session_save_path('/tmp');
} else {
    define('ENVIRONMENT', 'local');
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. TIMEZONE SETTINGS
date_default_timezone_set("Africa/Lagos");

// 3. DATABASE CREDENTIALS
if (ENVIRONMENT === 'cloud') {
    // These come from Vercel Environment Variables
    $servername = getenv('DB_HOST');
    $username   = getenv('DB_USER');
    $password   = getenv('DB_PASSWORD');
    $database   = getenv('DB_NAME');
} else {
    // Local XAMPP settings
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $database   = "attendance_system";
}

// 4. DATABASE CONNECTION
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
        die("Database connection failed. Please check your Vercel Environment Variables.");
    } else {
        die("Connection failed: " . $conn->connect_error);
    }
}
?>
