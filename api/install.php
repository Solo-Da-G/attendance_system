<?php
/**
 * ATTENDANCE SYSTEM — Installation Wizard
 * 
 * Run this once to set up the database tables and create the first admin account.
 * URL: http://localhost/attendance_system/install.php
 * 
 * After installation, DELETE this file for security!
 */

// Prevent running if already installed
$config_file = __DIR__ . "/../includes/config.php";
include($config_file);


$step     = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$message  = "";
$msg_type = "";
$installed = false;

// Check if admin table exists and has records
$check = $conn->query("SELECT COUNT(*) as c FROM admin");
if ($check && $check->fetch_assoc()['c'] > 0) {
    $installed = true;
}

// ================================================================
// STEP 2: Create tables + admin account
// ================================================================
if (isset($_POST['install'])) {
    $admin_user = trim($_POST['admin_username']);
    $admin_pass = trim($_POST['admin_password']);
    $admin_pass2 = trim($_POST['admin_password2']);

    if (empty($admin_user) || empty($admin_pass)) {
        $message = "Username and password are required";
        $msg_type = "error";
    } elseif ($admin_pass !== $admin_pass2) {
        $message = "Passwords do not match";
        $msg_type = "error";
    } else {
        // Create all tables
        $sqls = [
            // Admin table
            "CREATE TABLE IF NOT EXISTS `admin` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(100) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `role` ENUM('user','admin','super_admin') DEFAULT 'user',
                `status` ENUM('active','inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Staff table
            "CREATE TABLE IF NOT EXISTS `staff` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `staff_id` VARCHAR(50) NOT NULL UNIQUE,
                `full_name` VARCHAR(150) NOT NULL,
                `job_title` VARCHAR(100) DEFAULT NULL,
                `email` VARCHAR(150) DEFAULT NULL,
                `phone` VARCHAR(30) DEFAULT NULL,
                `photo` VARCHAR(255) DEFAULT NULL,
                `department` VARCHAR(100) DEFAULT NULL,
                `branch` VARCHAR(100) DEFAULT NULL,
                `fingerprint_id` VARCHAR(50) DEFAULT NULL,
                `fingerprint_data` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_staff_id` (`staff_id`),
                INDEX `idx_fingerprint_id` (`fingerprint_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Attendance table
            "CREATE TABLE IF NOT EXISTS `attendance` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `staff_id` VARCHAR(50) NOT NULL,
                `clock_in` DATETIME NOT NULL,
                `clock_out` DATETIME DEFAULT NULL,
                `status` VARCHAR(10) DEFAULT 'in',
                `total_hours` DECIMAL(5,2) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_staff_id` (`staff_id`),
                INDEX `idx_clock_in` (`clock_in`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // ZKTeco devices table
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        $errors = [];
        foreach ($sqls as $sql) {
            if (!$conn->query($sql)) {
                $errors[] = $conn->error;
            }
        }

        if (empty($errors)) {
            // Create admin account
            $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin (username, password, role, status) VALUES (?, ?, 'super_admin', 'active')");
            $stmt->bind_param("ss", $admin_user, $hashed);

            if ($stmt->execute()) {
                $message = "✅ Installation complete! All tables created and admin account set up.";
                $msg_type = "success";
                $installed = true;
                $step = 3;
            } else {
                $message = "Failed to create admin: " . $conn->error;
                $msg_type = "error";
            }
            $stmt->close();
        } else {
            $message = "Table creation errors: " . implode(", ", $errors);
            $msg_type = "error";
        }
    }
}

// Create uploads directory
if (!is_dir(__DIR__ . "/uploads")) {
    @mkdir(__DIR__ . "/uploads", 0755, true);
}
if (!is_dir(__DIR__ . "/logs")) {
    @mkdir(__DIR__ . "/logs", 0755, true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install — Attendance System</title>
<link rel="stylesheet" href="asset/css/style.css">
<style>
  body.install-page {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #312e81 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
  }
  .install-box {
    background: var(--surface);
    padding: 40px;
    border-radius: var(--radius-xl);
    width: min(550px, 95vw);
    box-shadow: var(--shadow-xl);
    animation: slideUp .5s var(--ease);
  }
  .install-box h2 {
    text-align: center;
    margin-bottom: 8px;
    font-size: 24px;
  }
  .install-box .subtitle {
    text-align: center;
    color: var(--text-muted);
    margin-bottom: 28px;
    font-size: 14px;
  }
  .step-dots {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 28px;
  }
  .step-dot {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    transition: all .3s;
  }
  .step-dot.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: #fff;
  }
  .step-dot.done {
    background: var(--success);
    color: #fff;
  }
  .step-dot.pending {
    background: var(--border);
    color: var(--text-muted);
  }
  .check-list {
    list-style: none;
    padding: 0;
    margin: 16px 0;
  }
  .check-list li {
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
  }
  .check-list li:last-child { border: none; }
  .check-icon { font-size: 18px; }
</style>
</head>
<body class="install-page">

<div class="install-box">
  <h2>📋 Attendance System</h2>
  <p class="subtitle">Installation Wizard</p>

  <!-- Step dots -->
  <div class="step-dots">
    <div class="step-dot <?php echo ($step >= 1) ? ($step > 1 ? 'done' : 'active') : 'pending'; ?>">1</div>
    <div class="step-dot <?php echo ($step >= 2) ? ($step > 2 ? 'done' : 'active') : 'pending'; ?>">2</div>
    <div class="step-dot <?php echo ($step >= 3) ? 'done' : 'pending'; ?>">3</div>
  </div>

  <?php if ($message): ?>
    <div class="msg-box <?php echo $msg_type; ?>" style="padding:12px 16px;border-radius:var(--radius);margin-bottom:20px;
      background:<?php echo $msg_type === 'success' ? 'var(--success-bg)' : 'var(--danger-bg)'; ?>;
      color:<?php echo $msg_type === 'success' ? '#065f46' : '#991b1b'; ?>;
      border:1px solid <?php echo $msg_type === 'success' ? '#6ee7b7' : '#fca5a5'; ?>;">
      <?php echo $message; ?>
    </div>
  <?php endif; ?>

  <?php if ($step === 1 && !$installed): ?>
    <!-- STEP 1: System Check -->
    <h3 style="margin-bottom:16px;">Step 1: System Check</h3>
    <ul class="check-list">
      <li>
        <span class="check-icon"><?php echo (PHP_VERSION_ID >= 70400) ? '✅' : '❌'; ?></span>
        PHP Version: <?php echo PHP_VERSION; ?> (7.4+ required)
      </li>
      <li>
        <span class="check-icon"><?php echo extension_loaded('mysqli') ? '✅' : '❌'; ?></span>
        MySQLi Extension
      </li>
      <li>
        <span class="check-icon"><?php echo extension_loaded('curl') ? '✅' : '❌'; ?></span>
        cURL Extension (for cloud sync)
      </li>
      <li>
        <span class="check-icon"><?php echo function_exists('socket_create') ? '✅' : '❌'; ?></span>
        Sockets Extension (for ZKTeco)
      </li>
      <li>
        <span class="check-icon"><?php echo $conn->ping() ? '✅' : '❌'; ?></span>
        Database Connection
      </li>
      <li>
        <span class="check-icon"><?php echo is_writable(__DIR__) ? '✅' : '❌'; ?></span>
        Directory Writable (for uploads)
      </li>
    </ul>
    <a href="?step=2"><button style="width:100%;margin-top:12px;">Continue to Setup →</button></a>

  <?php elseif ($step === 2 && !$installed): ?>
    <!-- STEP 2: Create Admin Account -->
    <h3 style="margin-bottom:16px;">Step 2: Create Admin Account</h3>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
      This will create all database tables and your first super admin account.
    </p>
    <form method="POST">
      <label>Admin Username</label>
      <input type="text" name="admin_username" placeholder="e.g. admin" required>
      <label>Password</label>
      <input type="password" name="admin_password" placeholder="Choose a strong password" required>
      <label>Confirm Password</label>
      <input type="password" name="admin_password2" placeholder="Re-enter password" required>
      <button type="submit" name="install" style="width:100%;margin-top:8px;">Install System</button>
    </form>

  <?php elseif ($step === 3 || $installed): ?>
    <!-- STEP 3: Done! -->
    <div style="text-align:center;">
      <p style="font-size:60px;margin-bottom:12px;">🎉</p>
      <h3>Installation Complete!</h3>
      <p style="color:var(--text-muted);margin:12px 0 24px;font-size:14px;">
        Your attendance system is ready. For security, please <strong>delete install.php</strong> from your server.
      </p>

      <div style="text-align:left;background:var(--surface-alt);padding:16px;border-radius:var(--radius);margin-bottom:20px;border:1px solid var(--border);">
        <p style="font-size:13px;font-weight:600;margin-bottom:8px;">Next Steps:</p>
        <ol style="font-size:13px;color:var(--text-muted);line-height:2;padding-left:20px;">
          <li>Login with your admin credentials</li>
          <li>Add employees on the Employees page</li>
          <li>Set up your ZKTeco device on the Devices page</li>
          <li>Start tracking attendance!</li>
        </ol>
      </div>

      <a href="index.php"><button style="width:100%;">Go to Login →</button></a>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
