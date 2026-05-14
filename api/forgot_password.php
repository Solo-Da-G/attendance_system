<?php
/**
 * FORGOT PASSWORD — Attendance System
 * 
 * Allows users to request a password reset via email.
 */

include(__DIR__ . "/../includes/config.php");

// Redirect if already logged in
if (isset($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit;
}

$message  = "";
$msg_type = "";

if (isset($_POST['reset_request'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $message = "Please enter your email address.";
        $msg_type = "error";
    } else {
        // Check if email exists in admin table
        $stmt = $conn->prepare("SELECT id, username FROM admin WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // 1. Generate new temporary password
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
            $new_pass = substr(str_shuffle($chars), 0, 10);
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

            // 2. Update database
            $update = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed_pass, $user['id']);
            
            if ($update->execute()) {
                // 3. Send Email
                $subject = "Password Reset — Attendance System";
                $body = "Hello " . $user['username'] . ",\n\n";
                $body .= "Your password has been reset as requested.\n";
                $body .= "New Password: " . $new_pass . "\n\n";
                $body .= "Please login at: " . CLOUD_URL . " and change your password immediately in Settings.\n\n";
                $body .= "Regards,\nAttendance System Team";

                $headers = "From: no-reply@micro-investment.com";

                // Attempt to send
                if (@mail($email, $subject, $body, $headers)) {
                    $message = "✅ Success! A new password has been sent to your email.";
                    $msg_type = "success";
                } else {
                    // Handled for localhost testing
                    if (ENVIRONMENT !== 'cloud') {
                       $message = "✅ Success! (Local Test Mode) New Password: <b>$new_pass</b> (Email failed to send because this is localhost)";
                    } else {
                       $message = "❌ Password reset but email failed to send. Please contact administrator.";
                    }
                    $msg_type = "success";
                }
            } else {
                $message = "❌ Failed to update password. Please try again.";
                $msg_type = "error";
            }
            $update->close();
        } else {
            $message = "❌ Email address not found in our system.";
            $msg_type = "error";
        }
        $stmt->close();
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
</head>
<body class="login-page">

<div class="login-container">
    <img src="./asset/img/miss_logo.png" alt="Logo" width="80">
    <h2>Reset Password</h2>
    <p style="color:var(--text-muted); font-size:14px; text-align:center; margin-bottom:20px;">
        Enter your email address and we'll send you a new temporary password.
    </p>

    <?php if ($message): ?>
        <p class="msg-<?php echo $msg_type; ?>"><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Recovery Email" required>
        <button type="submit" name="reset_request">Send Reset Link</button>
    </form>

    <div style="text-align:center; margin-top:20px;">
        <a href="index.php" style="color:var(--primary); font-weight:600; text-decoration:none;">← Back to Login</a>
    </div>
</div>

</body>
</html>


