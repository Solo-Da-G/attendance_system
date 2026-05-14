<?php
include(__DIR__ . "/../includes/config.php");

// Clear session
session_unset();
session_destroy();

// Clear the auth cookie
setcookie('auth_token', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

echo "<script>window.location.href='/index.php';</script>";
exit;
?>


