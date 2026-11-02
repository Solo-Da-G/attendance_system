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
            $has_mcp_admin = function_exists('db_has_column') && db_has_column($conn, 'admin', 'must_change_password');
            $sql_upd = $has_mcp_admin ? "UPDATE admin SET password = ?, must_change_password = 0 WHERE id = ?" : "UPDATE admin SET password = ? WHERE id = ?";
            $upd = $conn->prepare($sql_upd);
            $upd->bind_param("si", $new_hashed, $admin_id);
            if ($upd->execute()) {
                unset($_SESSION['require_password_change']);
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0f172a">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="TDS Attendance">
  <link rel="manifest" href="/manifest.json">
  <link rel="apple-touch-icon" href="/asset/img/tds_logo.png">
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
    <?php if (isset($_GET['first_login']) || !empty($_SESSION['require_password_change'])): ?>
      <div style="background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; padding:14px; border-radius:12px; margin-bottom:18px; font-size:14px; font-weight:600;">
        👋 <strong>Welcome!</strong> For account security, please update your temporary password to your own secure personal password.
      </div>
    <?php endif; ?>
    <?php if ($success) { ?>
      <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php } ?>

    <?php if ($error) { ?>
      <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php } ?>

    <form method="POST">
      <label>Current Password</label>
      <div class="password-input-wrap">
        <input type="password" id="currPassField" name="current_password" placeholder="Enter current password" required autocomplete="current-password">
        <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('currPassField', this)" aria-label="Show password" title="Show password">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <label>New Password</label>
      <div class="password-input-wrap">
        <input type="password" id="newPassField" name="new_password" placeholder="Enter new password" required autocomplete="new-password">
        <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('newPassField', this)" aria-label="Show password" title="Show password">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <label>Confirm New Password</label>
      <div class="password-input-wrap">
        <input type="password" id="confPassField" name="confirm_password" placeholder="Confirm new password" required autocomplete="new-password">
        <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('confPassField', this)" aria-label="Show password" title="Show password">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <button type="submit" name="change_password">Change Password</button>
    </form>

    <div class="back">
      <a href="dashboard.php">← Back to Dashboard</a>
    </div>
  </div>
</div>

<script>
function togglePassVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    const svg = btn ? btn.querySelector('svg') : null;
    if (svg) {
        if (isPass) {
            svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
            btn.style.color = '#2563eb';
            btn.setAttribute('aria-label', 'Hide password');
            btn.setAttribute('title', 'Hide password');
        } else {
            svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
            btn.style.color = '#94a3b8';
            btn.setAttribute('aria-label', 'Show password');
            btn.setAttribute('title', 'Show password');
        }
    }
}
</script>
</body>
</html>


