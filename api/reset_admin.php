<?php
include(__DIR__ . "/includes/config.php");

echo "<h2>🔧 Admin Recovery Tool</h2>";

// Show existing admins
$res = $conn->query("SELECT id, username, email FROM `admin`");
echo "<h3>Existing Admins:</h3><ul>";
$admin_exists = false;
$first_username = 'admin';

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "<li>Username: <strong>" . htmlspecialchars($row['username']) . "</strong> (Email: " . htmlspecialchars($row['email'] ?? 'none') . ")</li>";
        if ($row['username'] === 'admin') {
            $admin_exists = true;
        }
        $first_username = $row['username'];
    }
} else {
    echo "<li><em>No admin users found in database!</em></li>";
}
echo "</ul>";

$new_password = 'solomon01';
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

if (!$admin_exists && (!isset($res) || $res->num_rows === 0)) {
    // Database is completely empty of admins, create one
    echo "<h3>Creating new 'admin' user...</h3>";
    $stmt = $conn->prepare("INSERT INTO `admin` (username, password, role, status) VALUES ('admin', ?, 'super_admin', 'active')");
    $stmt->bind_param("s", $hashed);
    $stmt->execute();
    echo "<p>✅ Created username: <strong>admin</strong> with password: <strong>$new_password</strong></p>";
} else {
    // Reset the password for the first found admin or 'admin'
    $target_user = $admin_exists ? 'admin' : $first_username;
    echo "<h3>Resetting password for '$target_user'...</h3>";
    
    $stmt = $conn->prepare("UPDATE `admin` SET password = ? WHERE username = ?");
    $stmt->bind_param("ss", $hashed, $target_user);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo "<p>✅ Success! Password for '<strong>$target_user</strong>' is now '<strong>$new_password</strong>'.</p>";
    } else {
        // If 0 affected rows, the password was already solomon01 (hash matches? No, hash is different every time).
        echo "<p>✅ Done (updated " . $stmt->affected_rows . " rows).</p>";
    }
}

echo "<p><a href='/index.php' style='display:inline-block;padding:10px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:5px;'>Go to Login</a></p>";
echo "<p style='color:red;'><strong>IMPORTANT:</strong> Delete this file (reset_admin.php) from your project after logging in!</p>";
?>
