<?php
/**
 * FORGOT PASSWORD — Attendance System
 */

include(__DIR__ . "/includes/config.php");

$message  = "";
$msg_type = "";

function sendEmail($toEmail, $toName, $subject, $textBody, $htmlBody)
{
    $apiKey = getenv('BREVO_API_KEY') ?: $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? '';
    
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'BREVO_API_KEY not configured'];
    }

    $fromEmail = getenv('BREVO_FROM_EMAIL') ?: $_ENV['BREVO_FROM_EMAIL'] ?? $_SERVER['BREVO_FROM_EMAIL'] ?? 'no-reply@attendance.system';
    $fromName  = getenv('BREVO_FROM_NAME') ?: $_ENV['BREVO_FROM_NAME'] ?? $_SERVER['BREVO_FROM_NAME'] ?? 'Attendance System';

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
            // Generate a temporary password and set it immediately.
            // This matches your requirement: "send the temporary password to the person's email".
            $tempPass = substr(bin2hex(random_bytes(8)), 0, 10);
            $hashed = password_hash($tempPass, PASSWORD_DEFAULT);

            $upd = $conn->prepare("UPDATE $table SET password = ? WHERE id = ?");
            $upd->bind_param("si", $hashed, $user_id);

            if ($upd->execute()) {
                $subject = "Your Temporary Password — Attendance System";
                $body = "Hello $user_name,\n\n";
                $body .= "Here is your temporary password:\n\n";
                $body .= $tempPass . "\n\n";
                $body .= "Login and change your password immediately.\n\n";
                $body .= "Regards,\nAttendance System Team";

                $html = "<div style='font-family:Arial,sans-serif;line-height:1.6;color:#0f172a;'>
                    <h2 style='margin:0 0 12px;'>Temporary Password</h2>
                    <p>Hello " . htmlspecialchars($user_name) . ",</p>
                    <p>Your temporary password is:</p>
                    <div style='display:inline-block;background:#0f172a;color:#fff;padding:10px 14px;border-radius:10px;font-weight:800;letter-spacing:1px;'>
                        " . htmlspecialchars($tempPass) . "
                    </div>
                    <p style='margin-top:16px;'><strong>Important:</strong> Please login and change your password immediately.</p>
                    <p style='color:#64748b;font-size:13px;'>If you did not request this, contact your admin.</p>
                </div>";

                $send = sendEmail($email, $user_name, $subject, $body, $html);
                if ($send['ok']) {
                    $message = "✅ Temporary password sent to your email.";
                    $msg_type = "success";
                } else {
                    $message = "✅ Password reset done, but email could not be sent (email service not configured). Please contact admin to configure Brevo in Vercel env vars. Temporary password: <strong>" . htmlspecialchars($tempPass) . "</strong>";
                    $msg_type = "success";
                }
            } else {
                $message = "❌ Failed to set temporary password. Please try again.";
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
        .login-container p { color: #475569; font-size: 14.5px; margin-bottom: 24px; line-height: 1.6; }
        .login-container input {
            width: 100%;
            padding: 15px 18px;
            background-color: #f8fafc !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 12px;
            color: #0f172a !important;
            font-size: 15px;
            font-weight: 550;
            margin-bottom: 20px;
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
        <a href="index.php" style="color:var(--primary); font-weight:700; text-decoration:none; font-size:14px; transition: color 0.2s;">← Back to Login</a>
    </div>
</div>

</body>
</html>
