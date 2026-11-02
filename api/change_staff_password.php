<?php
include(__DIR__ . "/includes/config.php");

if (empty($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";
$success = "";

if (isset($_POST['change_password'])) {
    $current_pass = trim($_POST['current_password'] ?? '');
    $new_pass = trim($_POST['new_password']);
    $confirm_pass = trim($_POST['confirm_password']);

    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $error = "Please fill in all fields.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Passwords do not match.";
    } elseif ($new_pass === $_SESSION['staff_id']) {
        $error = "Your new password cannot be your Staff ID.";
    } elseif (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $staff_id = $_SESSION['staff_id'];
        $stmt = $conn->prepare("SELECT password FROM staff WHERE staff_id = ? LIMIT 1");
        $stmt->bind_param("s", $staff_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            $error = "Account not found.";
        } else {
            $stored_pass = $row['password'] ?: password_hash($staff_id, PASSWORD_DEFAULT);
            $ok = password_verify($current_pass, $stored_pass) || ($current_pass === $staff_id && empty($row['password']));
            if (!$ok) {
                $error = "Old password is incorrect.";
            } else {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $has_mcp = function_exists('db_has_column') && db_has_column($conn, 'staff', 'must_change_password');
                $sql_upd = $has_mcp ? "UPDATE staff SET password = ?, must_change_password = 0 WHERE staff_id = ?" : "UPDATE staff SET password = ? WHERE staff_id = ?";
                $upd = $conn->prepare($sql_upd);
                $upd->bind_param("ss", $hashed, $staff_id);
                if ($upd->execute()) {
                    unset($_SESSION['require_password_change']);
                    $success = "Password updated successfully.";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
                $upd->close();
            }
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
<title>Change Password — Attendance System</title>
<link rel="stylesheet" href="/asset/css/style.css">
<style>
    body {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .box {
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(20px);
        padding: 40px;
        border-radius: 24px;
        width: 100%;
        max-width: 400px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 20px 40px rgba(0,0,0,0.5);
    }
    h2 { color: white; margin-bottom: 10px; }
    p { color: rgba(255,255,255,0.7); margin-bottom: 25px; font-size: 14px; line-height: 1.5; }
    input {
        width: 100%;
        padding: 14px 20px;
        margin-bottom: 15px;
        background: #1e293b;
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 12px;
        color: white;
        box-sizing: border-box;
    }
    input:focus { outline: none; border-color: #3b82f6; }
    button {
        width: 100%;
        padding: 15px;
        background: #3b82f6;
        border: none;
        border-radius: 12px;
        color: white;
        font-weight: bold;
        cursor: pointer;
        transition: 0.3s;
    }
    button:hover { background: #2563eb; }
    .error { color: #ef4444; margin-bottom: 15px; font-size: 14px; }
    .password-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
        margin-bottom: 15px;
    }
    .password-input-wrap input {
        width: 100% !important;
        margin-bottom: 0 !important;
        padding-right: 48px !important;
    }
    button.password-toggle-btn,
    .password-toggle-btn {
        position: absolute !important;
        right: 12px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        width: 34px !important;
        height: 34px !important;
        min-width: 34px !important;
        min-height: 34px !important;
        padding: 0 !important;
        margin: 0 !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        cursor: pointer !important;
        color: #94a3b8 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 8px !important;
        transition: color 0.2s, background-color 0.2s !important;
        z-index: 5 !important;
    }
    button.password-toggle-btn:hover,
    .password-toggle-btn:hover,
    button.password-toggle-btn:focus,
    .password-toggle-btn:focus {
        transform: translateY(-50%) !important;
        color: #60a5fa !important;
        background: rgba(255, 255, 255, 0.08) !important;
        box-shadow: none !important;
        outline: none !important;
    }
    button.password-toggle-btn:active,
    .password-toggle-btn:active {
        transform: translateY(-50%) scale(0.92) !important;
    }
    button.password-toggle-btn svg,
    .password-toggle-btn svg {
        width: 20px !important;
        height: 20px !important;
        stroke: currentColor !important;
        stroke-width: 2.2 !important;
        fill: none !important;
        stroke-linecap: round !important;
        stroke-linejoin: round !important;
        display: block !important;
        pointer-events: none !important;
        margin: 0 !important;
    }
</style>
</head>
<body>
<div style="position:fixed; top:18px; left:18px; z-index:100; display:flex; gap:8px;">
    <button type="button" onclick="history.length > 1 ? history.back() : window.location='dashboard.php'" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#ffffff; padding:8px 14px; border-radius:10px; font-weight:700; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;" title="Go Back">
        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        <span>Back</span>
    </button>
    <button type="button" onclick="window.location.reload()" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#ffffff; padding:8px 14px; border-radius:10px; font-weight:700; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;" title="Refresh Page">
        <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" fill="none" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
        <span>Refresh</span>
    </button>
</div>
<div class="box">
    <h2>Change Password</h2>
    <p>Enter your old password, then choose a new secure password.</p>
    <?php if (isset($_GET['first_login']) || !empty($_SESSION['require_password_change'])): ?>
    <div style="background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.3);color:#93c5fd;padding:12px;border-radius:12px;margin-bottom:18px;font-size:13.5px;text-align:left;">
        👋 <strong>Welcome to the team!</strong> For account security, please update your default password to your own private password.
    </div>
    <?php endif; ?>
    
    <?php if ($error) echo "<div class='error'>$error</div>"; ?>
    <?php if ($success) echo "<div style='color:#86efac;margin-bottom:15px;font-size:14px;background:rgba(16,185,129,0.15);padding:12px;border-radius:12px;border:1px solid rgba(16,185,129,0.3);'>$success</div>"; ?>
    
    <form method="POST">
        <div class="password-input-wrap">
            <input type="password" id="staffCurrPass" name="current_password" placeholder="Old Password" required autocomplete="current-password">
            <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('staffCurrPass', this)" aria-label="Show password" title="Show password">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
        <div class="password-input-wrap">
            <input type="password" id="staffNewPass" name="new_password" placeholder="New Password (min 6 chars)" required autocomplete="new-password">
            <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('staffNewPass', this)" aria-label="Show password" title="Show password">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
        <div class="password-input-wrap">
            <input type="password" id="staffConfPass" name="confirm_password" placeholder="Confirm New Password" required autocomplete="new-password">
            <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('staffConfPass', this)" aria-label="Show password" title="Show password">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
        <button type="submit" name="change_password">Update Password</button>
    </form>
    <div style="margin-top:18px;">
        <a href="my_profile.php" style="color:#93c5fd;font-weight:700;text-decoration:none;">← Back to My Profile</a>
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
            btn.style.color = '#60a5fa';
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
