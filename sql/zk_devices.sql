-- ============================================================
-- ZKTeco Devices Table
-- Run this SQL in phpMyAdmin on your 'attendance_system' database
-- ============================================================

CREATE TABLE IF NOT EXISTS `zk_devices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_name` VARCHAR(100) NOT NULL DEFAULT 'ZKTeco Device',
  `ip_address` VARCHAR(45) NOT NULL,
  `port` INT NOT NULL DEFAULT 4370,
  `location` VARCHAR(150) DEFAULT NULL COMMENT 'e.g. Main Entrance, HR Office',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `api_key` VARCHAR(64) DEFAULT NULL COMMENT 'For external/cron sync auth',
  `last_sync` DATETIME DEFAULT NULL,
  `last_sync_status` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Add fingerprint_id index to staff table (faster lookups)
-- ============================================================
ALTER TABLE `staff` ADD INDEX `idx_fingerprint_id` (`fingerprint_id`);
