<?php
include(__DIR__ . "/../includes/config.php");

$error = "";
$idle_notice = (isset($_GET['reason']) && $_GET['reason'] === 'idle')
    ? 'You were signed out after 1 minute of inactivity.'
    : '';

// If already logged in, destroy session so they must log in again when visiting index.php
if (isset($_SESSION['admin_id']) || isset($_SESSION['staff_id'])) {
    if (isset($_SESSION['admin_id'])) {
        $conn->query("UPDATE `admin` SET auth_token = NULL WHERE id = " . (int)$_SESSION['admin_id']);
    } elseif (isset($_SESSION['staff_id'])) {
        $conn->query("UPDATE `staff` SET auth_token = NULL WHERE staff_id = '" . $conn->real_escape_string($_SESSION['staff_id']) . "'");
    }
    session_unset();
    session_destroy();
    setcookie('auth_token', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
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
                $upd = $conn->prepare("UPDATE `admin` SET auth_token = ? WHERE id = ?");
                if ($upd) {
                    $upd->bind_param("si", $token, $row['id']);
                    $upd->execute();
                    $upd->close();
                }
                setcookie('auth_token', $token, ['expires' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
                header("Location: dashboard.php");
                exit;
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
                // Add column if it doesn't exist just in case (will fail silently if mysqli exceptions are off)
                $conn->query("ALTER TABLE `staff` ADD COLUMN IF NOT EXISTS `auth_token` VARCHAR(64) DEFAULT NULL");
                
                $upd = $conn->prepare("UPDATE `staff` SET auth_token = ? WHERE id = ?");
                if ($upd) {
                    $upd->bind_param("si", $token, $row['id']);
                    $upd->execute();
                    $upd->close();
                }
                setcookie('auth_token', 'staff_' . $token, ['expires' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
                
                header("Location: dashboard.php");
                exit;
            }
        }

        $error = "Invalid username or password!";
    }
}
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
        --primary: #4f46e5;
        --primary-light: #818cf8;
        --login-bg-1: #0f172a;
        --login-bg-2: #1e1b4b;
        --login-bg-3: #020617;
        --login-bg-4: #172554;
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
        width: 400px;
        height: 400px;
        border-radius: 50%;
        filter: blur(100px);
        opacity: 0.4;
        animation: float 10s infinite alternate ease-in-out;
        z-index: 0;
        pointer-events: none;
    }
    body.login-page::before {
        background: var(--primary);
        top: -10%;
        left: -10%;
    }
    body.login-page::after {
        background: #06b6d4;
        bottom: -10%;
        right: -10%;
        animation-delay: -5s;
    }
    
    @keyframes float {
        0% { transform: translate(0, 0); }
        100% { transform: translate(40px, 40px); }
    }
    
    .login-container {
        position: relative;
        z-index: 10;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        border-left: 1px solid rgba(255, 255, 255, 0.2);
        padding: 48px;
        border-radius: 32px;
        width: 100%;
        max-width: 440px;
        text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), inset 0 0 0 1px rgba(255, 255, 255, 0.05);
        transform: translateY(0);
        transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .login-container:hover {
        transform: translateY(-4px);
    }
    
    .login-container img { 
        margin-bottom: 24px; 
        filter: drop-shadow(0 8px 16px rgba(255,255,255,0.15));
        transition: transform 0.3s ease;
    }
    .login-container img:hover {
        transform: scale(1.05);
    }
    
    .login-container h2 { 
        color: #ffffff; 
        margin-bottom: 8px; 
        font-weight: 800; 
        font-size: 32px; 
        letter-spacing: -0.5px;
    }
    .login-container p.subtitle { 
        color: rgba(255, 255, 255, 0.65); 
        font-size: 16px; 
        margin-bottom: 40px; 
    }
    
    .input-group {
        position: relative;
        margin-bottom: 20px;
        text-align: left;
    }
    .login-container input {
        width: 100%;
        padding: 16px 24px;
        background-color: rgba(15, 23, 42, 0.6) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        border-radius: 16px;
        color: #ffffff !important;
        font-size: 16px;
        font-weight: 500;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-sizing: border-box;
        display: block;
        font-family: inherit;
    }
    .login-container input::placeholder {
        color: rgba(255, 255, 255, 0.4) !important;
    }
    .login-container input:focus {
        background-color: rgba(15, 23, 42, 0.8) !important;
        border-color: var(--primary-light) !important;
        outline: none;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.25), inset 0 0 0 1px var(--primary-light);
        transform: translateY(-2px);
    }
    
    /* Force Autofill colors */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus {
        -webkit-text-fill-color: #ffffff !important;
        -webkit-box-shadow: 0 0 0px 1000px #0f172a inset !important;
        transition: background-color 5000s ease-in-out 0s;
    }
    
    .toggle-password {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        opacity: 0.5;
        z-index: 10;
        height: 24px;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
    }
    .toggle-password:hover { opacity: 1; transform: translateY(-50%) scale(1.1); }
    .toggle-password svg { width: 22px; height: 22px; fill: #ffffff; transition: fill 0.3s; }
    
    .forgot-pass {
        display: block;
        text-align: right;
        margin-top: -8px;
        margin-bottom: 32px;
        font-size: 14px;
        color: var(--primary-light);
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
        opacity: 0.9;
    }
    .forgot-pass:hover { 
        color: #ffffff; 
        opacity: 1;
        text-shadow: 0 0 8px rgba(255, 255, 255, 0.4);
    }
    
    .login-container button {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%);
        border: none;
        border-radius: 16px;
        color: #ffffff;
        font-weight: 700;
        font-size: 16px;
        letter-spacing: 0.5px;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        margin-top: 10px;
        box-shadow: 0 8px 20px -6px rgba(79, 70, 229, 0.6);
        position: relative;
        overflow: hidden;
    }
    .login-container button::after {
        content: '';
        position: absolute;
        top: 0; left: -100%; width: 100%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: all 0.5s ease;
    }
    .login-container button:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px -5px rgba(79, 70, 229, 0.8);
    }
    .login-container button:hover::after {
        left: 100%;
    }
    .login-container button:active {
        transform: translateY(1px);
        box-shadow: 0 5px 10px -2px rgba(79, 70, 229, 0.6);
    }
    
    .error-msg {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
        padding: 14px 20px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 500;
        margin-top: 24px;
        border: 1px solid rgba(239, 68, 68, 0.3);
        backdrop-filter: blur(8px);
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
    <img src="/asset/img/miss_logo.png" alt="Logo" width="90">
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
    
    <?php if (!empty($idle_notice)) echo "<p class='error-msg' style='background:rgba(59,130,246,0.2);color:#bfdbfe;border-color:rgba(59,130,246,0.4);'>$idle_notice</p>"; ?>
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
<h1>helo hello</h1>
</body>
</html>
