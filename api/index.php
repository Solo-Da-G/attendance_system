<?php
include(__DIR__ . "/../includes/config.php");

$error = "";

// If already logged in, go to dashboard
if (isset($_SESSION['admin_id']) || isset($_SESSION['staff_id'])) {
    header("Location: dashboard.php");
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
        $stmt = $conn->prepare("SELECT id, username, password, role FROM admin WHERE (username = ? OR email = ?) LIMIT 1");
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
                $upd = $conn->prepare("UPDATE admin SET auth_token = ? WHERE id = ?");
                $upd->bind_param("si", $token, $row['id']);
                $upd->execute();
                $upd->close();
                setcookie('auth_token', $token, ['expires' => time() + (30 * 24 * 60 * 60), 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
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
    :root {
        --login-bg: #0f172a;
        --login-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    }
    body.login-page {
        background: var(--login-bg);
        background: var(--login-gradient);
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
    }
    .login-container {
        background: rgba(15, 23, 42, 0.8); /* Darker, more solid background */
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 45px;
        border-radius: 32px;
        width: 100%;
        max-width: 420px;
        text-align: center;
        box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.8);
    }
    .login-container img { margin-bottom: 24px; filter: drop-shadow(0 0 15px rgba(255,255,255,0.2)); }
    .login-container h2 { color: #ffffff; margin-bottom: 10px; font-weight: 800; font-size: 28px; }
    .login-container p.subtitle { color: rgba(255,255,255,0.6); font-size: 15px; margin-bottom: 35px; }
    
    .input-group {
        position: relative;
        margin-bottom: 16px;
        text-align: left;
    }
    .login-container input {
        width: 100%;
        padding: 16px 24px;
        background-color: #1e293b !important; /* Solid dark background */
        border: 2px solid rgba(255, 255, 255, 0.1) !important;
        border-radius: 16px;
        color: #ffffff !important; /* Explicit white text */
        font-size: 16px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-sizing: border-box;
        display: block;
    }
    .login-container input::placeholder {
        color: rgba(255, 255, 255, 0.5) !important;
    }
    .login-container input:focus {
        background-color: #0f172a !important;
        border-color: #3b82f6 !important;
        outline: none;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3);
    }
    
    /* Force Autofill colors */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus {
        -webkit-text-fill-color: #ffffff !important;
        -webkit-box-shadow: 0 0 0px 1000px #1e293b inset !important;
        transition: background-color 5000s ease-in-out 0s;
    }
    
    .toggle-password {
        position: absolute;
        right: 18px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        opacity: 0.8;
        z-index: 10;
        height: 22px;
        display: flex;
        align-items: center;
    }
    .toggle-password:hover { opacity: 1; }
    .toggle-password svg { width: 22px; height: 22px; fill: #ffffff; }

    .forgot-pass {
        display: block;
        text-align: right;
        margin-top: -8px;
        margin-bottom: 24px;
        font-size: 14px;
        color: rgba(255,255,255,0.6);
        text-decoration: none;
        transition: color 0.3s;
    }
    .forgot-pass:hover { color: var(--primary-light); }

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
        margin-top: 10px;
    }
    .login-container button:hover {
        background: var(--primary-light);
        transform: translateY(-3px);
        box-shadow: 0 15px 30px -5px rgba(59, 130, 246, 0.5);
    }
    .error-msg {
        background: rgba(239, 68, 68, 0.2);
        color: #fecaca;
        padding: 14px;
        border-radius: 12px;
        font-size: 14px;
        margin-top: 24px;
        border: 1px solid rgba(239, 68, 68, 0.4);
    }
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
</body>
</html>
