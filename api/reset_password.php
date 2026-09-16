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
    <title>Set New Password — TDS Attendance System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #10439f;
            --primary-light: #2563eb;
            --primary-glow: rgba(16, 67, 159, 0.15);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #020617 0%, #0b193d 50%, #0f2c69 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(37,99,235,0.2) 0%, transparent 70%);
            top: -150px; right: -150px;
            border-radius: 50%;
            pointer-events: none;
        }
        body::after {
            content: '';
            position: absolute;
            width: 450px; height: 450px;
            background: radial-gradient(circle, rgba(249,168,37,0.15) 0%, transparent 70%);
            bottom: -150px; left: -150px;
            border-radius: 50%;
            pointer-events: none;
        }
        .reset-card {
            background: #ffffff;
            padding: 44px 36px;
            border-radius: 28px;
            width: 100%;
            max-width: 440px;
            text-align: center;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 2;
            animation: fadeIn 0.4s ease both;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo-wrap {
            display: inline-block;
            margin-bottom: 20px;
        }
        .logo-wrap img {
            max-width: 190px;
            width: 100%;
            height: auto;
            display: block;
        }
        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 67, 159, 0.08);
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            padding: 5px 14px;
            border-radius: 99px;
            margin-bottom: 12px;
            letter-spacing: 0.3px;
        }
        .reset-card h2 {
            color: #0f172a;
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .reset-card p.subtitle {
            color: #64748b;
            font-size: 14.5px;
            margin-bottom: 24px;
            line-height: 1.55;
            font-weight: 500;
        }
        .input-group {
            position: relative;
            margin-bottom: 18px;
            text-align: left;
        }
        .input-label {
            display: block;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .input-wrapper {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            display: flex;
            align-items: center;
            pointer-events: none;
        }
        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 44px;
            background-color: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            color: #0f172a;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            transition: all 0.25s ease;
        }
        .input-group input:focus {
            outline: none;
            background-color: #ffffff;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
        }
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border: none;
            border-radius: 14px;
            color: #ffffff;
            font-weight: 800;
            font-size: 15.5px;
            cursor: pointer;
            font-family: inherit;
            margin-top: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 24px -4px rgba(16, 67, 159, 0.4);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -4px rgba(16, 67, 159, 0.5);
        }
        .btn-submit:active { transform: translateY(1px); }
        .alert-msg {
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.5;
            margin-bottom: 22px;
            text-align: left;
        }
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 24px;
            color: var(--primary);
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary-light); text-decoration: underline; }
    </style>
</head>
<body>

<div class="reset-card">
    <div class="logo-wrap">
        <img src="/asset/img/tds_logo.png" alt="TDS Logo" onerror="this.parentElement.innerHTML='<h2 style=\'color:#10439f;font-weight:900;\'>TDS HITECH</h2>'">
    </div>
    <div class="badge-pill">🔑 Set New Password</div>
    <h2>Set New Password</h2>
    
    <?php if (!empty($message)): ?>
        <div class="alert-msg alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($valid_token): ?>
        <p class="subtitle">Please enter and confirm your new secure password below.</p>
        <form method="POST" autocomplete="off">
            <div class="input-group">
                <label class="input-label" for="new_password">New Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password" id="new_password" name="new_password" placeholder="Minimum 6 characters" required minlength="6">
                </div>
            </div>

            <div class="input-group">
                <label class="input-label" for="confirm_password">Confirm New Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your new password" required minlength="6">
                </div>
            </div>

            <button type="submit" name="update_password" class="btn-submit">Update Password →</button>
        </form>
    <?php else: ?>
        <div>
            <a href="index.php" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Return to Login Page
            </a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
