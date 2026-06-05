-- ============================================================
-- GEOFENCING SETUP — SQL
-- ============================================================

-- 1. Create Branches Table
CREATE TABLE IF NOT EXISTS `branches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `branch_name` VARCHAR(100) NOT NULL,
  `latitude` DECIMAL(10, 8) NOT NULL,
  `longitude` DECIMAL(11, 8) NOT NULL,
  `radius_meters` INT DEFAULT 200,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Add Location Columns to Attendance Table
ALTER TABLE `attendance` 
ADD COLUMN `lat_in` DECIMAL(10, 8) NULL AFTER `total_hours`,
ADD COLUMN `lng_in` DECIMAL(11, 8) NULL AFTER `lat_in`,
ADD COLUMN `lat_out` DECIMAL(10, 8) NULL AFTER `lng_in`,
ADD COLUMN `lng_out` DECIMAL(11, 8) NULL AFTER `lat_out`,
ADD COLUMN `is_geofenced` TINYINT(1) DEFAULT 0 AFTER `lng_out`;

-- 3. Link Staff to Branches (optional but recommended)
-- If you have multiple branches, we should add this to the staff table
ALTER TABLE `staff` ADD COLUMN `branch_id` INT DEFAULT NULL AFTER `branch`;
