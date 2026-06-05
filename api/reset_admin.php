<?php
include(__DIR__ . "/includes/config.php");

$username = 'admin';
$new_password = 'solomon01';
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE `admin` SET password = ? WHERE username = ?");
if ($stmt) {
    $stmt->bind_param("ss", $hashed, $username);
    if ($stmt->execute()) {
        echo "<h2>✅ Success</h2>";
        echo "<p>Password for '<strong>$username</strong>' has been reset to '<strong>$new_password</strong>'.</p>";
        echo "<p><a href='/index.php'>Go to Login</a></p>";
        echo "<p style='color:red;'><strong>IMPORTANT:</strong> Delete this file (reset_admin.php) from your project after logging in!</p>";
    } else {
        echo "<h2>❌ Error</h2>";
        echo "<p>Could not update password: " . $stmt->error . "</p>";
    }
    $stmt->close();
} else {
    echo "<h2>❌ Error</h2>";
    echo "<p>Database prepare error: " . $conn->error . "</p>";
}
?>
