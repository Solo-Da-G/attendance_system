<?php
include(__DIR__ . "/includes/config.php");

$error = "";
$idle_notice = (isset($_GET['reason']) && $_GET['reason'] === 'idle')
    ? 'You were signed out after 1 minute of inactivity.'
    : '';

// If already logged in, do NOT destroy the session (this was causing "random logouts" on Vercel).
// Instead, just send the user to the dashboard.
if (isset($_SESSION['admin_id']) || isset($_SESSION['staff_id'])) {
    header("Location: /dashboard.php");
    exit;
}

// Handle login form
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please fill in both fields!";
    } else {
        // 1. Try Admin Login first
        $stmt = $conn->prepare("SELECT id, username, password, role FROM `admin` WHERE (username = ? OR email = ?) LIMIT 1");
        if (!$stmt) {
            die("MySQL Prepare Error (Admin Table): " . $conn->error);
        }
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin']    = $row['username'];
                $_SESSION['role']     = $row['role'];
                
                $token = bin2hex(random_bytes(32));
                
                $saved = false;
                if (function_exists('db_has_column') && db_has_column($conn, 'admin', 'auth_token')) {
                    $upd = $conn->prepare("UPDATE `admin` SET auth_token = ? WHERE id = ?");
                    if ($upd) {
                        $upd->bind_param("si", $token, $row['id']);
                        $saved = (bool)$upd->execute();
                        $upd->close();
                    }
                }

                if (!$saved) {
                    // On Vercel, PHP sessions may not persist reliably, so we MUST be able to save/restore auth_token.
                    $error = "Server setup incomplete (admin auth token not saved). Please run api/fix_database.php on your cloud DB or add the admin.auth_token column (and ensure DB user has ALTER/UPDATE permission).";
                    goto render_form;
                } else {
                    $secure = function_exists('is_https_request') ? is_https_request() : true;
                    setcookie('auth_token', $token, ['expires' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
                    header("Location: /dashboard.php");
                    exit;
                }
            }
        }

        // 2. Try Staff Login if Admin fails
        $stmt = $conn->prepare("SELECT id, staff_id, full_name, password FROM staff WHERE (staff_id = ? OR email = ?) LIMIT 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $stored_pass = $row['password'] ?: password_hash($row['staff_id'], PASSWORD_DEFAULT);
            
            if (password_verify($password, $stored_pass) || ($password === $row['staff_id'] && empty($row['password']))) {
                $_SESSION['staff_id'] = $row['staff_id'];
                $_SESSION['admin']    = $row['full_name'];
                $_SESSION['role']     = 'staff';
                
                // Force password change if using default ID
                if ($password === $row['staff_id'] && (empty($row['password']) || password_verify($row['staff_id'], $row['password']))) {
                    $_SESSION['require_password_change'] = true;
                }
                
                // Set auth_token for staff to survive Vercel statelessness
                $token = bin2hex(random_bytes(32));

                $saved = false;
                if (function_exists('db_has_column') && db_has_column($conn, 'staff', 'auth_token')) {
                    $upd = $conn->prepare("UPDATE `staff` SET auth_token = ? WHERE id = ?");
                    if ($upd) {
                        $upd->bind_param("si", $token, $row['id']);
                        $saved = (bool)$upd->execute();
                        $upd->close();
                    }
                }

                if (!$saved) {
                    $error = "Server setup incomplete (staff auth token not saved). Please run api/fix_database.php on your cloud DB or add the staff.auth_token column (and ensure DB user has ALTER/UPDATE permission).";
                    goto render_form;
                } else {
                    $secure = function_exists('is_https_request') ? is_https_request() : true;
                    setcookie('auth_token', 'staff_' . $token, ['expires' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
                    header("Location: /dashboard.php");
                    exit;
                }
            }
        }

        if (empty($error)) {
            $error = "Invalid username or password!";
        }
    }
}
render_form:
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Attendance System</title>
<link rel="stylesheet" href="/asset/css/style.css">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    
    :root {
        --primary: #10439f;
        --primary-light: #2563eb;
        --primary-glow: rgba(16, 67, 159, 0.15);
        --login-bg-1: #0b0f19;
        --login-bg-2: #111827;
        --login-bg-3: #1f2937;
        --login-bg-4: #030712;
    }
    
    body.login-page {
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: linear-gradient(-45deg, var(--login-bg-1), var(--login-bg-2), var(--login-bg-3), var(--login-bg-4));
        background-size: 400% 400%;
        animation: gradientBG 15s ease infinite;
        position: relative;
        overflow: hidden;
    }
    
    @keyframes gradientBG {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    
    /* Add some ambient floating orbs */
    body.login-page::before,
    body.login-page::after {
        content: '';
        position: absolute;
        width: 450px;
        height: 450px;
        border-radius: 50%;
        filter: blur(120px);
        opacity: 0.3;
        animation: float 12s infinite alternate ease-in-out;
        z-index: 0;
        pointer-events: none;
    }
    body.login-page::before {
        background: var(--primary);
        top: -10%;
        left: -10%;
    }
    body.login-page::after {
        background: #0ea5e9;
        bottom: -10%;
        right: -10%;
        animation-delay: -6s;
    }
    
    @keyframes float {
        0% { transform: translate(0, 0); }
        100% { transform: translate(60px, 60px); }
    }
 
    .login-container {
        position: relative;
        z-index: 10;
        background: #ffffff;
        padding: 40px 36px;
        border-radius: 24px;
        width: 100%;
        max-width: 380px;
        text-align: center;
        box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.05);
        transform: translateY(0);
        transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-sizing: border-box;
    }
    .login-container:hover {
        transform: translateY(-4px);
        box-shadow: 0 35px 70px -15px rgba(0, 0, 0, 0.5);
    }
    
    .login-container img { 
        margin-bottom: 28px; 
        transition: transform 0.3s ease;
    }
    .login-container img:hover {
        transform: scale(1.03);
    }
    
    .login-container h2 { 
        color: #0f172a; 
        margin-bottom: 6px; 
        font-weight: 800; 
        font-size: 26px; 
        letter-spacing: -0.5px;
    }
    .login-container p.subtitle { 
        color: #475569; 
        font-size: 14.5px; 
        margin-bottom: 28px; 
        font-weight: 500;
    }
 
    .login-container h2,
    .login-container p.subtitle,
    .login-container form {
        animation: fadeUp .7s cubic-bezier(0.4, 0, 0.2, 1) both;
    }
    .login-container p.subtitle { animation-delay: .05s; }
    .login-container form { animation-delay: .10s; }
 
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    
    .input-group {
        position: relative;
        margin-bottom: 16px;
        text-align: left;
    }
    .login-container input {
        width: 100%;
        padding: 15px 18px;
        background-color: #f8fafc !important;
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 12px;
        color: #0f172a !important;
        font-size: 15px;
        font-weight: 550;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        box-sizing: border-box;
        display: block;
        font-family: inherit;
    }
    .login-container input::placeholder {
        color: #94a3b8 !important;
    }
    .login-container input:focus {
        background-color: #ffffff !important;
        border-color: var(--primary) !important;
        outline: none;
        box-shadow: 0 0 0 4px var(--primary-glow);
    }
    
    /* Force Autofill colors */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus {
        -webkit-text-fill-color: #0f172a !important;
        -webkit-box-shadow: 0 0 0px 1000px #f8fafc inset !important;
        transition: background-color 5000s ease-in-out 0s;
    }
    
    .toggle-password {
        position: absolute;
        right: 18px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        opacity: 0.6;
        z-index: 10;
        height: 24px;
        display: flex;
        align-items: center;
        transition: all 0.2s ease;
    }
    .toggle-password:hover { opacity: 1; transform: translateY(-50%) scale(1.08); }
    .toggle-password svg { width: 22px; height: 22px; fill: #475569; transition: fill 0.3s; }
    
    .forgot-pass {
        display: block;
        text-align: right;
        margin-top: -8px;
        margin-bottom: 24px;
        font-size: 13.5px;
        color: var(--primary);
        font-weight: 700;
        text-decoration: none;
        transition: all 0.2s;
    }
    .forgot-pass:hover { 
        color: var(--primary-light); 
        text-decoration: underline;
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
        letter-spacing: 0.5px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        margin-top: 10px;
        box-shadow: 0 8px 20px -6px rgba(16, 67, 159, 0.4);
        position: relative;
        overflow: hidden;
    }
    .login-container button::after {
        content: '';
        position: absolute;
        top: 0; left: -100%; width: 100%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
        transition: all 0.6s ease;
    }
    .login-container button:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 25px -4px rgba(16, 67, 159, 0.5);
    }
    .login-container button:hover::after {
        left: 100%;
    }
    .login-container button:active {
        transform: translateY(1px);
        box-shadow: 0 4px 10px -2px rgba(16, 67, 159, 0.4);
    }
    
    .error-msg {
        background: rgba(239, 68, 68, 0.08);
        color: #b91c1c;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        margin-top: 24px;
        border: 1px solid rgba(239, 68, 68, 0.16);
        animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
    }
    
    @keyframes shake {
        10%, 90% { transform: translate3d(-1px, 0, 0); }
        20%, 80% { transform: translate3d(2px, 0, 0); }
        30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
        40%, 60% { transform: translate3d(4px, 0, 0); }
    }
</style>
</head>
<body class="login-page">
<div class="login-container">
    <img src="/asset/img/tds_logo.png" alt="TDS Logo" style="max-width: 190px; width: 100%; height: auto; margin-bottom: 20px; display: inline-block;">
    <h2>Welcome Back</h2>
    <p class="subtitle">Sign in to your dashboard</p>
    
    <form method="POST" action="index.php" autocomplete="off">
        <div class="input-group">
            <input type="text" name="username" placeholder="Username or Staff ID" required>
        </div>
        
        <div class="input-group">
            <input type="password" name="password" id="passwordField" placeholder="Password" required>
            <div class="toggle-password" onclick="togglePass()">
                <svg id="eyeIcon" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
            </div>
        </div>

        <a href="forgot_password.php" class="forgot-pass">Forgot Password?</a>
        
        <button type="submit" name="login">Sign In</button>
    </form>
    
    <?php if (!empty($idle_notice)) echo "<p class='error-msg' style='background:rgba(59,130,246,0.08);color:#1e40af;border-color:rgba(59,130,246,0.16);'>$idle_notice</p>"; ?>
    <?php if (!empty($error)) echo "<p class='error-msg'>$error</p>"; ?>
</div>

<script>
    function togglePass() {
        const passField = document.getElementById('passwordField');
        const eyeIcon = document.getElementById('eyeIcon');
        
        if (passField.type === 'password') {
            passField.type = 'text';
            eyeIcon.style.opacity = "1";
            eyeIcon.style.fill = "#3b82f6";
        } else {
            passField.type = 'password';
            eyeIcon.style.opacity = "0.5";
            eyeIcon.style.fill = "white";
        }
    }
</script>
<script src="/asset/js/ui-enhancements.js" defer></script>
</body>
</html>
