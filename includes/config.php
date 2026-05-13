<?php
/**
 * ATTENDANCE SYSTEM — Configuration
 */

// 1. FORCE ERRORS ON
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

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
$conn = mysqli_init();
if (ENVIRONMENT === 'cloud') {
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    $conn->real_connect($servername, $username, $password, $database, null, null, MYSQLI_CLIENT_SSL);
} else {
    $conn->real_connect($servername, $username, $password, $database);
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 6. TEST WITHOUT CUSTOM HANDLER FIRST
// (I am commenting this out to see if the page loads at all)
/*
class DatabaseSessionHandler implements SessionHandlerInterface {
    private $db;
    public function __construct($db) { $this->db = $db; }
    public function open($savePath, $sessionName) { return true; }
    public function close() { return true; }
    public function read($id) {
        $stmt = $this->db->prepare("SELECT data FROM sessions WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) { return (string)$row['data']; }
        return "";
    }
    public function write($id, $data) {
        $ts = time();
        $stmt = $this->db->prepare("REPLACE INTO sessions (id, data, timestamp) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $id, $data, $ts);
        return $stmt->execute();
    }
    public function destroy($id) {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->bind_param("s", $id);
        return $stmt->execute();
    }
    public function gc($maxlifetime) {
        $ts = time() - $maxlifetime;
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE timestamp < ?");
        $stmt->execute();
        return true;
    }
}
$handler = new DatabaseSessionHandler($conn);
session_set_save_handler($handler, true);
*/

if (ENVIRONMENT === 'cloud') {
    session_save_path('/tmp');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!-- Config Loaded -->";
?>
