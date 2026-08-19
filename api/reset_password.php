<?php
/**
 * RESET PASSWORD — Attendance System
 */

include(__DIR__ . "/includes/config.php");

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
    $stmt = $conn->prepare("SELECT id, reset_token_expires FROM $table WHERE email = ? AND reset_token = ? LIMIT 1");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 1) {
        $user_row = $res->fetch_assoc();
        $exp = $user_row['reset_token_expires'] ?? null;
        if (!empty($exp) && strtotime($exp) < time()) {
            $message = "This reset link is invalid or has expired.";
            $msg_type = "error";
        } else {
            $valid_token = true;
        }
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
        
        $upd = $conn->prepare("UPDATE $table SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
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
        :root {
            --primary: #10439f;
            --primary-light: #2563eb;
            --primary-glow: rgba(16, 67, 159, 0.15);
        }
        body.login-page {
            background: linear-gradient(135deg, #0b0f19 0%, #111827 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .login-container {
            background: #ffffff;
            padding: 40px 36px;
            border-radius: 24px;
            width: 100%;
            max-width: 420px;
            text-align: center;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.05);
            box-sizing: border-box;
        }
        .login-container h2 { color: #0f172a; margin-bottom: 10px; font-weight: 800; font-size: 26px; }
        .login-container p { color: #475569; font-size: 14.5px; margin-bottom: 24px; }
        .login-container input {
            width: 100%;
            padding: 15px 18px;
            background-color: #f8fafc !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 12px;
            color: #0f172a !important;
            font-size: 15px;
            font-weight: 550;
            margin-bottom: 16px;
            box-sizing: border-box;
            font-family: inherit;
        }
        .login-container input:focus {
            outline: none;
            background-color: #ffffff !important;
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px var(--primary-glow);
        }
        .login-container input::placeholder { color: #94a3b8 !important; }

        /* Force Autofill colors */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: #0f172a !important;
            -webkit-box-shadow: 0 0 0px 1000px #f8fafc inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        .login-container button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-weight: 750;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 10px;
            box-shadow: 0 8px 20px -6px rgba(16, 67, 159, 0.4);
        }
        .login-container button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px -4px rgba(16, 67, 159, 0.5);
        }
        .msg-success { color: #166534; font-size: 14px; margin-bottom: 20px; background: rgba(22, 101, 52, 0.08); padding: 15px; border-radius: 12px; border: 1px solid rgba(22, 101, 52, 0.16); font-weight: 600; }
        .msg-error { color: #b91c1c; font-size: 14px; margin-bottom: 20px; background: rgba(185, 28, 28, 0.08); padding: 15px; border-radius: 12px; border: 1px solid rgba(185, 28, 28, 0.16); font-weight: 600; }
    </style>
</head>
<body class="login-page">

<div class="login-container">
    <img src="/asset/img/tds_logo.png" alt="TDS Logo" style="max-width: 180px; width: 100%; height: auto; margin-bottom: 20px; display: inline-block;">
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
