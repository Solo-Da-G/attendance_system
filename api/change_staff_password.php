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
                $upd = $conn->prepare("UPDATE staff SET password = ? WHERE staff_id = ?");
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
</style>
</head>
<body>
<div class="box">
    <h2>Change Password</h2>
    <p>Enter your old password, then choose a new secure password.</p>
    
    <?php if ($error) echo "<div class='error'>$error</div>"; ?>
    <?php if ($success) echo "<div style='color:#86efac;margin-bottom:15px;font-size:14px;background:rgba(16,185,129,0.15);padding:12px;border-radius:12px;border:1px solid rgba(16,185,129,0.3);'>$success</div>"; ?>
    
    <form method="POST">
        <input type="password" name="current_password" placeholder="Old Password" required>
        <input type="password" name="new_password" placeholder="New Password (min 6 chars)" required>
        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
        <button type="submit" name="change_password">Update Password</button>
    </form>
    <div style="margin-top:18px;">
        <a href="my_profile.php" style="color:#93c5fd;font-weight:700;text-decoration:none;">← Back to My Profile</a>
    </div>
</div>
</body>
</html>
