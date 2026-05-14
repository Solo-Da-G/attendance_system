<?php
include(__DIR__ . "/../includes/config.php");

// Only staff who need a password change should be here
if (empty($_SESSION['staff_id']) || empty($_SESSION['require_password_change'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if (isset($_POST['change_password'])) {
    $new_pass = trim($_POST['new_password']);
    $confirm_pass = trim($_POST['confirm_password']);

    if (empty($new_pass) || empty($confirm_pass)) {
        $error = "Please fill in all fields.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Passwords do not match.";
    } elseif ($new_pass === $_SESSION['staff_id']) {
        $error = "Your new password cannot be your Staff ID.";
    } elseif (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Update password in DB
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE staff SET password = ? WHERE staff_id = ?");
        $stmt->bind_param("ss", $hashed, $_SESSION['staff_id']);
        
        if ($stmt->execute()) {
            // Clear the flag so they can access the dashboard
            unset($_SESSION['require_password_change']);
            
            // Also update the database session cookie so it doesn't log them out immediately if stateless
            // They are already logged in via $_SESSION, so this is just a seamless transition
            
            echo "<script>alert('Password updated successfully! Welcome to your dashboard.'); window.location.href='dashboard.php';</script>";
            exit;
        } else {
            $error = "Failed to update password. Please try again.";
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
    <h2>Update Your Password</h2>
    <p>For security reasons, you must change your default password before accessing your dashboard.</p>
    
    <?php if ($error) echo "<div class='error'>$error</div>"; ?>
    
    <form method="POST">
        <input type="password" name="new_password" placeholder="New Password (min 6 chars)" required>
        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
        <button type="submit" name="change_password">Save & Continue</button>
    </form>
</div>
</body>
</html>
