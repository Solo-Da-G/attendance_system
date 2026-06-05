<?php
/**
 * PASSWORD RESET AUTO-SETUP
 * 
 * Updates the admin table to support emails and password recovery.
 */

include("includes/config.php");

echo "<h2>🔑 Password Reset Database Setup</h2>";
echo "<pre>";

$queries = [
    // 1. Add email column to admin table
    "ALTER TABLE `admin` ADD COLUMN IF NOT EXISTS `email` VARCHAR(150) NULL AFTER `password`"
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "✅ SUCCESS: " . substr($sql, 0, 50) . "...<br>";
    } else {
        echo "❌ ERROR: " . $conn->error . "<br>";
    }
}

echo "</pre>";
echo "<p><b>Done!</b> Email support has been added to the user table.</p>";
echo "<a href='user.php'><button>Go to User Management</button></a>";
?>
