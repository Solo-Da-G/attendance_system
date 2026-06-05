<?php
include(__DIR__ . "/includes/config.php");

if (empty($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$success = "";
$error = "";

if (isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $error = "All fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        $admin_id = (int)$_SESSION['admin_id'];
        $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            $error = "Admin account not found";
        } elseif (!password_verify($current_password, $row['password'])) {
            $error = "Current password is incorrect";
        } else {
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
            $upd->bind_param("si", $new_hashed, $admin_id);
            if ($upd->execute()) {
                $success = "Password changed successfully";
            } else {
                $error = "Failed to update password";
            }
            $upd->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
  <div style="background: linear-gradient(135deg, #0f172a, #1e293b); color:white; padding:32px; border-radius:24px; margin-bottom:22px;">
    <h2 style="margin:0; font-size:28px;">🔒 Change Password</h2>
    <p style="opacity:0.75; margin-top:10px;">Update your admin password securely</p>
  </div>

  <div class="box" style="max-width:520px;">
    <?php if ($success) { ?>
      <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php } ?>

    <?php if ($error) { ?>
      <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php } ?>

    <form method="POST">
      <label>Current Password</label>
      <input type="password" name="current_password" placeholder="Enter current password" required>
      <label>New Password</label>
      <input type="password" name="new_password" placeholder="Enter new password" required>
      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" placeholder="Confirm new password" required>
      <button type="submit" name="change_password">Change Password</button>
    </form>

    <div class="back">
      <a href="dashboard.php">← Back to Dashboard</a>
    </div>
  </div>
</div>

</body>
</html>


