<?php
/**
 * GEOFENCING AUTO-SETUP
 * 
 * Automatically updates the database for geofencing support.
 */

include("includes/config.php");

echo "<h2>🛠️ Geofencing Database Setup</h2>";
echo "<pre>";

$queries = [
    // 1. Create Branches Table
    "CREATE TABLE IF NOT EXISTS `branches` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `branch_name` VARCHAR(100) NOT NULL,
      `latitude` DECIMAL(10, 8) NOT NULL,
      `longitude` DECIMAL(11, 8) NOT NULL,
      `radius_meters` INT DEFAULT 200,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 2. Add Location Columns to Attendance Table (Checking if they exist first)
    "ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `lat_in` DECIMAL(10, 8) NULL AFTER `total_hours`",
    "ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `lng_in` DECIMAL(11, 8) NULL AFTER `lat_in`",
    "ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `lat_out` DECIMAL(10, 8) NULL AFTER `lng_in`",
    "ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `lng_out` DECIMAL(11, 8) NULL AFTER `lat_out`",
    "ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `is_geofenced` TINYINT(1) DEFAULT 0 AFTER `lng_out`"
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "✅ SUCCESS: " . substr($sql, 0, 50) . "...<br>";
    } else {
        echo "❌ ERROR: " . $conn->error . "<br>";
    }
}

echo "</pre>";
echo "<p><b>Done!</b> You can now delete this script and proceed to manage branches.</p>";
echo "<a href='dashboard.php'><button>Back to Dashboard</button></a>";
?>
