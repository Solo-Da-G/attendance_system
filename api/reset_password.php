<?php
/**
 * RESET PASSWORD — Attendance System
 */

include(__DIR__ . "/../includes/config.php");

$message = "";
$msg_type = "";
$valid_token = false;

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$type  = $_GET['type'] ?? 'admin'; // 'admin' or 'staff'

if (empty($token) || empty($email)) {
    $message = "Invalid or missing reset token.";
    $msg_type = "error";
} else {
    // Validate token
    $table = ($type === 'staff') ? 'staff' : 'admin';
    $stmt = $conn->prepare("SELECT id FROM $table WHERE email = ? AND reset_token = ? LIMIT 1");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 1) {
        $valid_token = true;
        $user_row = $res->fetch_assoc();
    } else {
        $message = "This reset link is invalid or has expired.";
        $msg_type = "error";
    }
    $stmt->close();
}

// Handle password update
if (isset($_POST['update_password']) && $valid_token) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 6) {
        $message = "Password must be at least 6 characters long.";
        $msg_type = "error";
    } elseif ($new_pass !== $confirm_pass) {
        $message = "Passwords do not match.";
        $msg_type = "error";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $user_id = $user_row['id'];
        
        $upd = $conn->prepare("UPDATE $table SET password = ?, reset_token = NULL WHERE id = ?");
        $upd->bind_param("si", $hashed, $user_id);
        
        if ($upd->execute()) {
            $message = "✅ Password updated! You can now login with your new password.";
            $msg_type = "success";
            $valid_token = false; // Hide form after success
        } else {
            $message = "❌ Failed to update password.";
            $msg_type = "error";
        }
        $upd->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — Attendance System</title>
    <link rel="stylesheet" href="/asset/css/style.css">
    <style>
        body.login-page {
            background: #0f172a;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .login-container {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 45px;
            border-radius: 32px;
            width: 100%;
            max-width: 420px;
            text-align: center;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.7);
        }
        .login-container h2 { color: #ffffff; margin-bottom: 10px; font-weight: 800; font-size: 28px; }
        .login-container p { color: rgba(255,255,255,0.7); font-size: 15px; margin-bottom: 30px; }
        .login-container input {
            width: 100%;
            padding: 16px 20px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            color: #ffffff;
            font-size: 16px;
            margin-bottom: 16px;
            box-sizing: border-box;
        }
        .login-container input::placeholder { color: rgba(255,255,255,0.4); }
        .login-container button {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            border: none;
            border-radius: 16px;
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s var(--ease);
            margin-top: 10px;
        }
        .login-container button:hover { background: var(--primary-light); transform: translateY(-3px); }
        .msg-success { color: #86efac; font-size: 14px; margin-bottom: 20px; background: rgba(16, 185, 129, 0.15); padding: 15px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.3); }
        .msg-error { color: #fca5a5; font-size: 14px; margin-bottom: 20px; background: rgba(239, 68, 68, 0.15); padding: 15px; border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.3); }
    </style>
</head>
<body class="login-page">

<div class="login-container">
    <img src="/asset/img/miss_logo.png" alt="Logo" width="80">
    <h2>New Password</h2>
    
    <?php if ($message): ?>
        <div class="msg-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($valid_token): ?>
        <p>Please enter and confirm your new secure password.</p>
        <form method="POST">
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="submit" name="update_password">Reset Password</button>
        </form>
    <?php else: ?>
        <div style="margin-top:20px;">
            <a href="index.php" style="color:var(--primary); font-weight:600; text-decoration:none;">Go to Login Page</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
