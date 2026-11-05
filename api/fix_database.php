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
    "ALTER TABLE `admin` ADD COLUMN `last_backup_time` DATETIME NULL",
    "ALTER TABLE `admin` ADD COLUMN `auto_backup_freq` VARCHAR(20) DEFAULT 'never'",
    "ALTER TABLE `admin` ADD COLUMN `auto_backup_type` VARCHAR(30) DEFAULT 'both'",
    "ALTER TABLE `admin` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0",
    
    // Add & modify columns in staff table
    "ALTER TABLE `staff` ADD COLUMN `password` VARCHAR(255) NULL",
    "ALTER TABLE `staff` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE `staff` ADD COLUMN `reset_token` VARCHAR(64) NULL",
    "ALTER TABLE `staff` ADD COLUMN `reset_token_expires` DATETIME NULL",
    "ALTER TABLE `staff` ADD COLUMN `auth_token` VARCHAR(64) NULL",
    "ALTER TABLE `staff` ADD COLUMN `deleted_at` DATETIME NULL",
    "ALTER TABLE `staff` ADD COLUMN `clock_lat` DECIMAL(10,8) NULL",
    "ALTER TABLE `staff` ADD COLUMN `clock_lng` DECIMAL(11,8) NULL",
    "ALTER TABLE `staff` ADD COLUMN `clock_radius` INT DEFAULT 300",
    "ALTER TABLE `staff` ADD COLUMN `branch_id` INT DEFAULT NULL AFTER `branch`",
    "ALTER TABLE `staff` ADD COLUMN `fingerprint_id` VARCHAR(255) NULL",
    "ALTER TABLE `staff` MODIFY COLUMN `fingerprint_id` VARCHAR(255) NULL DEFAULT NULL",
    "ALTER TABLE `staff` ADD COLUMN `fingerprint_data` LONGTEXT NULL",
    "ALTER TABLE `staff` MODIFY COLUMN `fingerprint_data` LONGTEXT NULL",
    "ALTER TABLE `staff` MODIFY COLUMN `photo` LONGTEXT NULL",
    
    // Add missing columns to attendance table
    "ALTER TABLE `attendance` ADD COLUMN `source` VARCHAR(20) DEFAULT 'web'",
    "ALTER TABLE `attendance` ADD COLUMN `photo_in` TEXT NULL",
    "ALTER TABLE `attendance` ADD COLUMN `photo_out` TEXT NULL",
    "ALTER TABLE `attendance` ADD COLUMN `lat_in` DECIMAL(10,8) NULL",
    "ALTER TABLE `attendance` ADD COLUMN `lng_in` DECIMAL(11,8) NULL",
    "ALTER TABLE `attendance` ADD COLUMN `lat_out` DECIMAL(10,8) NULL",
    "ALTER TABLE `attendance` ADD COLUMN `lng_out` DECIMAL(11,8) NULL",
    "ALTER TABLE `attendance` ADD COLUMN `is_geofenced` TINYINT(1) DEFAULT 0",
    "ALTER TABLE `attendance` ADD COLUMN `branch_in` VARCHAR(100) NULL",
    "ALTER TABLE `attendance` ADD COLUMN `branch_out` VARCHAR(100) NULL",
    
    // Create branches table if not exists
    "CREATE TABLE IF NOT EXISTS `branches` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `branch_name` VARCHAR(100) NOT NULL,
        `latitude` DECIMAL(10,8) NOT NULL,
        `longitude` DECIMAL(11,8) NOT NULL,
        `radius_meters` INT DEFAULT 200,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Create field_work_comments table if not exists
    "CREATE TABLE IF NOT EXISTS `field_work_comments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `staff_id` VARCHAR(50) NOT NULL,
        `full_name` VARCHAR(200),
        `branch` VARCHAR(100),
        `comment` TEXT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `is_read` TINYINT(1) DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Create audit_log table if not exists
    "CREATE TABLE IF NOT EXISTS `audit_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `staff_id` VARCHAR(50),
        `full_name` VARCHAR(200),
        `event_type` VARCHAR(80) NOT NULL,
        `event_detail` TEXT,
        `ip_address` VARCHAR(60),
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
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

echo "\n--- Staff Photo Optimization ---\n";
if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
    $res = $conn->query("SELECT id, staff_id, full_name, photo FROM staff WHERE photo IS NOT NULL AND LENGTH(photo) > 100000");
    $opt_count = 0;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rawPhoto = $row['photo'];
            if (preg_match('#^data:(image/[a-zA-Z0-9\+\-\.]+);base64,(.+)$#s', $rawPhoto, $matches)) {
                $binary = base64_decode($matches[2]);
                if ($binary !== false) {
                    $src = @imagecreatefromstring($binary);
                    if ($src !== false) {
                        $w = imagesx($src);
                        $h = imagesy($src);
                        $maxDim = 450;
                        $ratio = min($maxDim / max($w, 1), $maxDim / max($h, 1));
                        $nw = max(1, (int)round($w * $ratio));
                        $nh = max(1, (int)round($h * $ratio));
                        $dst = imagecreatetruecolor($nw, $nh);
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        ob_start();
                        imagejpeg($dst, null, 82);
                        $opt = ob_get_clean();
                        imagedestroy($dst);
                        imagedestroy($src);
                        if (!empty($opt) && strlen($opt) < strlen($binary)) {
                            $newPhoto = 'data:image/jpeg;base64,' . base64_encode($opt);
                            $up = $conn->prepare("UPDATE staff SET photo = ? WHERE id = ?");
                            if ($up) {
                                $up->bind_param("si", $newPhoto, $row['id']);
                                $up->execute();
                                $up->close();
                                $oldKb = round(strlen($binary) / 1024);
                                $newKb = round(strlen($opt) / 1024);
                                echo "⚡ Optimized photo for {$row['full_name']} ({$row['staff_id']}): {$oldKb} KB -> {$newKb} KB\n";
                                $opt_count++;
                            }
                        }
                    }
                }
            }
        }
    }
    echo "Total photos optimized: {$opt_count}\n";
} else {
    echo "ℹ️ GD extension not loaded in this environment. Client-side canvas compressor optimizes uploads automatically.\n";
}

echo "\n--- Benin City Branch Coordinates Sync (66b Mission Road) ---\n";
// 1. Look for branch with 'benin' in its name
$bBeninRes = $conn->query("SELECT id, branch_name, latitude, longitude, radius_meters FROM branches WHERE LOWER(branch_name) LIKE '%benin%'");
$beninUpdated = false;

if ($bBeninRes && $bBeninRes->num_rows > 0) {
    while ($br = $bBeninRes->fetch_assoc()) {
        $brId = (int)$br['id'];
        $upBr = $conn->prepare("UPDATE branches SET latitude = 6.339722, longitude = 5.625278, radius_meters = GREATEST(COALESCE(radius_meters, 300), 300) WHERE id = ?");
        if ($upBr) {
            $upBr->bind_param("i", $brId);
            $upBr->execute();
            $upBr->close();
            echo "✅ Updated branch '{$br['branch_name']}' (ID: {$brId}) coordinates to Lat: 6.339722, Lng: 5.625278 (66b Mission Road, Benin City, Radius: >=300m)\n";
            $beninUpdated = true;
        }
    }
}

if (!$beninUpdated) {
    // If no branch with 'benin' exists yet, insert TDS Benin City branch
    $ins = $conn->prepare("INSERT INTO branches (branch_name, latitude, longitude, radius_meters) VALUES ('TDS Benin City', 6.339722, 5.625278, 300)");
    if ($ins && $ins->execute()) {
        echo "✅ Created new branch 'TDS Benin City' at 66b Mission Road (Lat: 6.339722, Lng: 5.625278, Radius: 300m)\n";
        $ins->close();
    }
}

// 2. Also update all staff members assigned to the Benin branch
$upStaff = $conn->query("UPDATE staff SET clock_lat = 6.339722, clock_lng = 5.625278, clock_radius = 300 WHERE LOWER(branch) LIKE '%benin%'");
if ($upStaff) {
    $aff = $conn->affected_rows;
    echo "✅ Synced GPS coordinates (6.339722, 5.625278) for {$aff} staff member(s) assigned to Benin branch.\n";
}

echo "\n</pre>";
echo "<p style='color:green;font-weight:bold;'>✅ Database fix, Benin coordinates sync & photo optimization completed!</p>";
echo "<a href='/manage_branches.php' style='display:inline-block;margin-top:20px;margin-right:12px;padding:10px 20px;background:#10b981;color:white;text-decoration:none;border-radius:8px;font-weight:bold;'>View Branches & Geofencing →</a>";
echo "<a href='/employees.php' style='display:inline-block;margin-top:20px;margin-right:12px;padding:10px 20px;background:#2563eb;color:white;text-decoration:none;border-radius:8px;'>View Employees →</a>";
echo "<a href='/dashboard.php' style='display:inline-block;margin-top:20px;padding:10px 20px;background:#4f46e5;color:white;text-decoration:none;border-radius:8px;'>Go to Dashboard →</a>";
?>
