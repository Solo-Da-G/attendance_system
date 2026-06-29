<?php
include(__DIR__ . "/includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("<h2 style='color:red;text-align:center;margin-top:100px;font-family:sans-serif;'>Access Denied.</h2>");
}

$success = "";
$error = "";

/* UPDATE SETTINGS */
if (isset($_POST['update_settings'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $admin_id = $_SESSION['admin_id'];

    if (empty($username)) {
        $error = "Username cannot be empty";
    } else {

        $auto_backup = $_POST['auto_backup_freq'] ?? 'never';

        if (!empty($password)) {
            // Hash new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "UPDATE admin SET username = ?, password = ?, auto_backup_freq = ? WHERE id = ?"
            );
            $stmt->bind_param("sssi", $username, $hashedPassword, $auto_backup, $admin_id);
        } else {
            // Update username and backup freq
            $stmt = $conn->prepare(
                "UPDATE admin SET username = ?, auto_backup_freq = ? WHERE id = ?"
            );
            $stmt->bind_param("ssi", $username, $auto_backup, $admin_id);
        }

        if ($stmt->execute()) {
            $success = "Settings updated successfully";
            $_SESSION['admin'] = $username;
        } else {
            $error = "Failed to update settings";
        }

        $stmt->close();
    }
}

/* FETCH CURRENT ADMIN */
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT username, auto_backup_freq FROM `admin` WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
if (!isset($admin['auto_backup_freq'])) {
    // Failsafe if column isn't created yet
    $admin['auto_backup_freq'] = 'never';
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
    <div class="settings-box" style="max-width:500px; margin: 40px auto; background:var(--surface); padding:30px; border-radius:var(--radius-lg); box-shadow:var(--shadow);">
        <h2 style="margin-bottom:20px;">⚙️ Admin Settings</h2>

        <?php if ($success) { ?>
            <p class="badge badge-success" style="display:block; margin-bottom:15px; text-align:center; padding:10px;"><?php echo $success; ?></p>
        <?php } ?>

        <?php if ($error) { ?>
            <p class="badge badge-danger" style="display:block; margin-bottom:15px; text-align:center; padding:10px;"><?php echo $error; ?></p>
        <?php } ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username"
                   value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" required>

            <label style="margin-top:15px; display:block;">New Password (leave blank to keep current)</label>
            <input type="password" name="password" placeholder="Enter new password">

            <div style="background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); padding: 15px; border-radius: 12px; margin-top: 25px;">
                <h4 style="margin: 0 0 10px; color: #6d28d9;">🔄 Automated Database Backups</h4>
                <p style="font-size: 13px; color: #4b5563; margin: 0 0 10px; line-height: 1.4;">Select how often the system should automatically email a database backup (.sql file) to the admin email address.</p>
                <select name="auto_backup_freq" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); font-size: 15px; background: white;">
                    <option value="never" <?php echo ($admin['auto_backup_freq'] === 'never') ? 'selected' : ''; ?>>Never (Manual only)</option>
                    <option value="24_hours" <?php echo ($admin['auto_backup_freq'] === '24_hours') ? 'selected' : ''; ?>>Every 24 Hours</option>
                    <option value="7_days" <?php echo ($admin['auto_backup_freq'] === '7_days') ? 'selected' : ''; ?>>Every 7 Days</option>
                    <option value="14_days" <?php echo ($admin['auto_backup_freq'] === '14_days') ? 'selected' : ''; ?>>Every 14 Days</option>
                    <option value="30_days" <?php echo ($admin['auto_backup_freq'] === '30_days') ? 'selected' : ''; ?>>Every 30 Days</option>
                </select>
            </div>

            <button type="submit" name="update_settings" style="margin-top:25px; width:100%;">Save Settings</button>
        </form>

        <div class="back-link" style="margin-top:20px; text-align:center;">
            <a href="dashboard.php" style="color:var(--primary); text-decoration:none; font-weight:500;">← Back to Dashboard</a>
        </div>
    </div>

    <div class="footer" style="text-align:center; margin-top:40px;">
      &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Collins
    </div>
</div>

</body>
</html>
