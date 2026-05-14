<?php
include(__DIR__ . "/../includes/config.php");

// Clear auth token from Database for maximum security
if (isset($_SESSION['admin_id'])) {
    $conn->query("UPDATE `admin` SET auth_token = NULL WHERE id = " . (int)$_SESSION['admin_id']);
} elseif (isset($_SESSION['staff_id'])) {
    $conn->query("UPDATE `staff` SET auth_token = NULL WHERE staff_id = '" . $conn->real_escape_string($_SESSION['staff_id']) . "'");
}

// Clear session and session cookie
session_unset();
session_destroy();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear the auth cookie in the browser
setcookie('auth_token', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

header("Location: index.php");
exit;
?>


