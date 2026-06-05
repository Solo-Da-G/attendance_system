<?php
/**
 * FORGOT PASSWORD — Attendance System
 */

include(__DIR__ . "/includes/config.php");

$message  = "";
$msg_type = "";

function sendSendGridEmail($toEmail, $toName, $subject, $textBody, $htmlBody)
{
    $apiKey = getenv('SENDGRID_API_KEY') ?: '';
    if ($apiKey === '') return ['ok' => false, 'error' => 'SENDGRID_API_KEY not configured'];
    if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'cURL not available'];

    $fromEmail = getenv('SENDGRID_FROM_EMAIL') ?: '';
    $fromName  = getenv('SENDGRID_FROM_NAME') ?: 'Attendance System';
    if ($fromEmail === '') return ['ok' => false, 'error' => 'SENDGRID_FROM_EMAIL not configured'];

    $payload = [
        'personalizations' => [[
            'to' => [[
                'email' => $toEmail,
                'name'  => $toName ?: $toEmail,
            ]],
        ]],
        'from' => [
            'email' => $fromEmail,
            'name'  => $fromName,
        ],
        'subject' => $subject,
        'content' => [
            ['type' => 'text/plain', 'value' => $textBody],
            ['type' => 'text/html', 'value' => $htmlBody],
        ],
    ];

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) return ['ok' => false, 'error' => $err];
    if ($code >= 200 && $code < 300) return ['ok' => true];
    return ['ok' => false, 'error' => 'HTTP ' . $code . ' response: ' . substr((string)$resp, 0, 300)];
}

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
            $expires_at = date("Y-m-d H:i:s", time() + (60 * 60));
            
            // Store token in the correct table
            $upd = $conn->prepare("UPDATE $table SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $upd->bind_param("ssi", $token, $expires_at, $user_id);
            
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

                $html = "<div style='font-family:Arial,sans-serif;line-height:1.6;color:#0f172a;'>
                    <h2 style='margin:0 0 12px;'>Password Reset</h2>
                    <p>Hello " . htmlspecialchars($user_name) . ",</p>
                    <p>You requested a password reset for your Attendance System account.</p>
                    <p><a href='" . htmlspecialchars($reset_link) . "' style='display:inline-block;padding:12px 18px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:10px;font-weight:700;'>Reset Password</a></p>
                    <p style='color:#64748b;font-size:13px;'>This link expires in 1 hour. If you did not request this, ignore this email.</p>
                </div>";

                $send = sendSendGridEmail($email, $user_name, $subject, $body, $html);
                if ($send['ok']) {
                    $message = "✅ Success! A reset link has been sent to your email.";
                    $msg_type = "success";
                } else {
                    $message = "✅ Token generated, but email could not be sent. Use this link: <br><a href='$reset_link' style='color:#3b82f6;'>Reset My Password</a>";
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
