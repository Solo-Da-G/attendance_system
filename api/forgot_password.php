<?php
/**
 * FORGOT PASSWORD — Attendance System
 * Secure token-based password recovery
 */

include(__DIR__ . "/includes/config.php");

$message  = "";
$msg_type = "";

function sendEmail($toEmail, $toName, $subject, $textBody, $htmlBody)
{
    $apiKey = getenv('BREVO_API_KEY') ?: $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? '';
    
    if ($apiKey === '') {
        // Fallback to PHP native mail() if available
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: TDS Attendance <no-reply@attendance.system>\r\n";
        if (@mail($toEmail, $subject, $htmlBody, $headers)) {
            return ['ok' => true];
        }
        return ['ok' => false, 'error' => 'BREVO_API_KEY not configured and standard mail service unavailable.'];
    }

    $fromEmail = getenv('BREVO_FROM_EMAIL') ?: $_ENV['BREVO_FROM_EMAIL'] ?? $_SERVER['BREVO_FROM_EMAIL'] ?? 'no-reply@attendance.system';
    $fromName  = getenv('BREVO_FROM_NAME') ?: $_ENV['BREVO_FROM_NAME'] ?? $_SERVER['BREVO_FROM_NAME'] ?? 'TDS Attendance System';

    $postData = [
        'sender'      => ['name' => $fromName, 'email' => $fromEmail],
        'to'          => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
        'subject'     => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => $textBody
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => [
            'api-key: ' . $apiKey,
            'accept: application/json',
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($err) return ['ok' => false, 'error' => $err];
    if ($code >= 200 && $code < 300) return ['ok' => true];
    return ['ok' => false, 'error' => 'HTTP ' . $code . ' response: ' . substr((string)$resp, 0, 300)];
}

if (isset($_POST['reset_request'])) {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
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
        
        // Auto-assign admin email if solyno04@gmail.com is not found
        if (!$user_found && $email === 'solyno04@gmail.com') {
            $upd = $conn->query("UPDATE `admin` SET email = 'solyno04@gmail.com' LIMIT 1");
            if ($upd) {
                $user_found = true;
                $table = "admin";
                $res = $conn->query("SELECT id, username FROM `admin` WHERE email = 'solyno04@gmail.com' LIMIT 1");
                $row = $res->fetch_assoc();
                $user_id = $row['id'];
                $user_name = $row['username'];
            }
        }

        if ($user_found) {
            // Generate secure 64-char crypto token valid for 1 hour
            $reset_token = bin2hex(random_bytes(32));
            $expires_at  = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            // Save token and expiry in the database
            $upd = $conn->prepare("UPDATE `$table` SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $upd->bind_param("ssi", $reset_token, $expires_at, $user_id);

            if ($upd->execute()) {
                // Build absolute reset link URL
                $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                $protocol = $is_https ? 'https://' : 'http://';
                $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $resetUrl = "{$protocol}{$host}/reset_password.php?token={$reset_token}&email=" . urlencode($email) . "&type={$table}";

                $subject = "Password Reset Request — TDS Attendance System";
                
                $body = "Hello " . ($user_name ?: 'User') . ",\n\n";
                $body .= "We received a request to reset your password for TDS Attendance System.\n\n";
                $body .= "Please click the link below or copy and paste it into your browser to set a new password:\n";
                $body .= $resetUrl . "\n\n";
                $body .= "This link will expire in 1 hour.\n";
                $body .= "If you did not request a password reset, you can safely ignore this email.\n\n";
                $body .= "Regards,\nTDS Attendance System Team";

                $html = "<!DOCTYPE html>
                <html>
                <head>
                    <meta charset='utf-8'>
                    <style>
                        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 30px; color: #1e293b; }
                        .email-container { max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
                        .email-header { background: linear-gradient(135deg, #10439f, #2563eb); color: #ffffff; padding: 28px 32px; text-align: center; }
                        .email-body { padding: 32px; line-height: 1.6; }
                        .btn-reset { display: inline-block; background: #10439f; color: #ffffff !important; padding: 14px 28px; border-radius: 10px; font-weight: 700; text-decoration: none; font-size: 15px; margin: 20px 0; }
                        .email-footer { padding: 20px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class='email-container'>
                        <div class='email-header'>
                            <h2 style='margin:0; font-size:22px;'>TDS Attendance System</h2>
                            <p style='margin:6px 0 0; opacity:0.9; font-size:14px;'>Secure Password Recovery</p>
                        </div>
                        <div class='email-body'>
                            <p style='font-size:16px; font-weight:600;'>Hello " . htmlspecialchars($user_name ?: 'User') . ",</p>
                            <p>We received a request to reset your password. Click the button below to choose a new password:</p>
                            <div style='text-align: center;'>
                                <a href='" . htmlspecialchars($resetUrl) . "' class='btn-reset' target='_blank'>Reset My Password</a>
                            </div>
                            <p style='font-size:13px; color:#64748b; margin-top:20px;'>Or copy and paste this link into your browser:<br>
                            <a href='" . htmlspecialchars($resetUrl) . "' style='color:#2563eb; word-break:break-all;'>" . htmlspecialchars($resetUrl) . "</a></p>
                            <p style='font-size:13px; color:#64748b;'><strong>Note:</strong> This link is valid for <strong>1 hour</strong>. If you did not make this request, you can safely ignore this email.</p>
                        </div>
                        <div class='email-footer'>
                            &copy; " . date('Y') . " TDS Hitech Solutions Limited. All rights reserved.
                        </div>
                    </div>
                </body>
                </html>";

                $send = sendEmail($email, $user_name, $subject, $body, $html);
                if ($send['ok']) {
                    $message = "✅ A password reset link has been sent to your email address (<strong>" . htmlspecialchars($email) . "</strong>). Please check your inbox and spam folder.";
                    $msg_type = "success";
                } else {
                    $message = "⚠️ We were unable to deliver the email. Please ensure your email configuration is set up or contact your system administrator.";
                    $msg_type = "error";
                }
            } else {
                $message = "❌ Failed to initiate password reset. Please try again.";
                $msg_type = "error";
            }
            $upd->close();
        } else {
            $message = "❌ Email address not found in our records. Please verify the email or contact your administrator.";
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
    <title>Forgot Password — TDS Attendance System</title>
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
        /* Ambient lighting orbs */
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
            margin-bottom: 26px;
            line-height: 1.55;
            font-weight: 500;
        }
        .input-group {
            position: relative;
            margin-bottom: 20px;
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
    <div class="badge-pill">🔒 Password Recovery</div>
    <h2>Reset Your Password</h2>
    <p class="subtitle">Enter your registered email address and we'll send you a secure link to reset your credentials.</p>

    <?php if (!empty($message)): ?>
        <div class="alert-msg alert-<?php echo $msg_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="forgot_password.php" autocomplete="off">
        <div class="input-group">
            <label class="input-label" for="email">Account Email Address</label>
            <div class="input-wrapper">
                <span class="input-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </span>
                <input type="email" id="email" name="email" placeholder="name@company.com" required autofocus>
            </div>
        </div>
        <button type="submit" name="reset_request" class="btn-submit">Send Reset Link →</button>
    </form>

    <div>
        <a href="index.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back to Sign In
        </a>
    </div>
</div>

</body>
</html>
