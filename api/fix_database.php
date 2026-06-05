<?php
/**
 * RUN THIS ONCE to fix database schema for Vercel deployment
 * Visit: https://attendance-system-delta-five.vercel.app/api/fix_database.php
 * DELETE AFTER RUNNING!
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: text/html; charset=utf-8');
echo "<h2>🔧 Database Fix Script</h2><pre>";

$queries = [
    // Add missing columns to admin table (These will error if column exists, which is fine to ignore)
    "ALTER TABLE `admin` ADD COLUMN `email` VARCHAR(150) NULL AFTER `password`",
    "ALTER TABLE `admin` ADD COLUMN `reset_token` VARCHAR(64) NULL",
    "ALTER TABLE `admin` ADD COLUMN `reset_token_expires` DATETIME NULL",
    "ALTER TABLE `admin` ADD COLUMN `auth_token` VARCHAR(64) NULL",
    "ALTER TABLE `admin` ADD COLUMN `deleted_at` DATETIME NULL",
    
    // Add missing columns to staff table
    "ALTER TABLE `staff` ADD COLUMN `password` VARCHAR(255) NULL",
    "ALTER TABLE `staff` ADD COLUMN `reset_token` VARCHAR(64) NULL",
    "ALTER TABLE `staff` ADD COLUMN `reset_token_expires` DATETIME NULL",
    "ALTER TABLE `staff` ADD COLUMN `auth_token` VARCHAR(64) NULL",
    "ALTER TABLE `staff` ADD COLUMN `deleted_at` DATETIME NULL",
    "ALTER TABLE `staff` ADD COLUMN `clock_lat` DECIMAL(10,8) NULL",
    "ALTER TABLE `staff` ADD COLUMN `clock_lng` DECIMAL(11,8) NULL",
    "ALTER TABLE `staff` ADD COLUMN `clock_radius` INT DEFAULT 300",
    
    // Add missing columns to attendance table
    "ALTER TABLE `attendance` ADD COLUMN `source` VARCHAR(20) DEFAULT 'web'",
    "ALTER TABLE `attendance` ADD COLUMN `photo_in` TEXT NULL",
    "ALTER TABLE `attendance` ADD COLUMN `photo_out` TEXT NULL",
    "ALTER TABLE `attendance` ADD COLUMN `lat_in` DECIMAL(10,8) NULL",
    "ALTER TABLE `attendance` ADD COLUMN `lng_in` DECIMAL(11,8) NULL",
    "ALTER TABLE `attendance` ADD COLUMN `lat_out` DECIMAL(10,8) NULL",
    "ALTER TABLE `attendance` ADD COLUMN `lng_out` DECIMAL(11,8) NULL",
    "ALTER TABLE `attendance` ADD COLUMN `is_geofenced` TINYINT(1) DEFAULT 0",
    
    // Create branches table if not exists
    "CREATE TABLE IF NOT EXISTS `branches` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `branch_name` VARCHAR(100) NOT NULL,
        `latitude` DECIMAL(10,8) NOT NULL,
        `longitude` DECIMAL(11,8) NOT NULL,
        `radius_meters` INT DEFAULT 200,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Create zk_devices table if not exists
    "CREATE TABLE IF NOT EXISTS `zk_devices` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `device_name` VARCHAR(100) NOT NULL DEFAULT 'ZKTeco Device',
        `ip_address` VARCHAR(45) NOT NULL,
        `port` INT NOT NULL DEFAULT 4370,
        `location` VARCHAR(150) DEFAULT NULL,
        `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `api_key` VARCHAR(64) DEFAULT NULL,
        `last_sync` DATETIME DEFAULT NULL,
        `last_sync_status` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "✅ " . substr($sql, 0, 60) . "...\n";
    } else {
        echo "❌ ERROR: " . $conn->error . "\n";
    }
}

echo "\n</pre>";
echo "<p style='color:green;font-weight:bold;'>✅ Database fix completed! Now delete this file.</p>";
echo "<a href='/dashboard.php' style='display:inline-block;margin-top:20px;padding:10px20px;background:#4f46e5;color:white;text-decoration:none;border-radius:8px;'>Go to Dashboard →</a>";
?>
