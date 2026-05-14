<?php
/**
 * FORGOT PASSWORD — Attendance System
 */

include(__DIR__ . "/../includes/config.php");

$message  = "";
$msg_type = "";

if (isset($_POST['reset_request'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $message = "Please enter your email address.";
        $msg_type = "error";
    } else {
        // 1. Search in Admin table
        $stmt = $conn->prepare("SELECT id, username FROM `admin` WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $admin_res = $stmt->get_result();
        $stmt->close();

        // 2. Search in Staff table
        $stmt = $conn->prepare("SELECT id, full_name as username FROM staff WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $staff_res = $stmt->get_result();
        $stmt->close();

        $user_found = false;
        $table = "";
        $user_id = null;
        $user_name = "";

        if ($admin_res->num_rows === 1) {
            $user_found = true;
            $table = "admin";
            $row = $admin_res->fetch_assoc();
            $user_id = $row['id'];
            $user_name = $row['username'];
        } elseif ($staff_res->num_rows === 1) {
            $user_found = true;
            $table = "staff";
            $row = $staff_res->fetch_assoc();
            $user_id = $row['id'];
            $user_name = $row['username'];
        }

        if ($user_found) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            
            // Store token in the correct table
            $upd = $conn->prepare("UPDATE $table SET reset_token = ? WHERE id = ?");
            $upd->bind_param("si", $token, $user_id);
            
            if ($upd->execute()) {
                // Construct reset link
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $reset_link = "$protocol://$host/api/reset_password.php?token=$token&email=" . urlencode($email) . "&type=$table";

                // Email content
                $subject = "Password Reset Request — Attendance System";
                $body = "Hello $user_name,\n\n";
                $body .= "You requested a password reset for your Attendance System account.\n";
                $body .= "Click the link below to set a new password:\n\n";
                $body .= $reset_link . "\n\n";
                $body .= "If you did not request this, please ignore this email.\n\n";
                $body .= "Regards,\nAttendance System Team";

                $headers = "From: no-reply@" . $host;

                // Attempt to send (might fail on Vercel without SMTP)
                if (@mail($email, $subject, $body, $headers)) {
                    $message = "✅ Success! A reset link has been sent to your email.";
                    $msg_type = "success";
                } else {
                    // Fallback for environment verification
                    $message = "✅ Token generated! In a live environment, an email would be sent. For now, you can use this link: <br><a href='$reset_link' style='color:#3b82f6;'>Reset My Password</a>";
                    $msg_type = "success";
                }
            } else {
                $message = "❌ Error generating reset token.";
                $msg_type = "error";
            }
            $upd->close();
        } else {
            $message = "❌ Email address not found in our records.";
            $msg_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Attendance System</title>
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
        .login-container p { color: rgba(255,255,255,0.7); font-size: 15px; margin-bottom: 30px; line-height: 1.6; }
        .login-container input {
            width: 100%;
            padding: 16px 24px;
            background-color: #1e293b !important;
            border: 2px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 16px;
            color: #ffffff !important;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            box-sizing: border-box;
        }
        .login-container input::placeholder { color: rgba(255,255,255,0.5) !important; }

        /* Force Autofill colors */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff !important;
            -webkit-box-shadow: 0 0 0px 1000px #1e293b inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }
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
        }
        .login-container button:hover { background: var(--primary-light); transform: translateY(-3px); }
        .msg-success { color: #86efac; font-size: 14px; margin-bottom: 20px; background: rgba(16, 185, 129, 0.15); padding: 15px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.3); }
        .msg-error { color: #fca5a5; font-size: 14px; margin-bottom: 20px; background: rgba(239, 68, 68, 0.15); padding: 15px; border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.3); }
    </style>
</head>
<body class="login-page">

<div class="login-container">
    <img src="/asset/img/miss_logo.png" alt="Logo" width="80">
    <h2>Reset Access</h2>
    <p>Enter your recovery email address and we'll send you a secure link to reset your password.</p>

    <?php if ($message): ?>
        <div class="msg-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Email Address" required>
        <button type="submit" name="reset_request">Send Reset Link</button>
    </form>

    <div style="margin-top:25px;">
        <a href="index.php" style="color:var(--primary); font-weight:600; text-decoration:none; font-size:14px;">← Back to Login</a>
    </div>
</div>

</body>
</html>
