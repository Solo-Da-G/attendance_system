<?php
include(__DIR__ . "/../includes/config.php");

$error = "";

// If already logged in, go to dashboard
if (isset($_SESSION['admin_id']) || isset($_SESSION['staff_id'])) {
    echo "<script>window.location.href='/dashboard.php';</script>";
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
        $stmt = $conn->prepare("SELECT id, username, password, role FROM admin WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin']    = $row['username'];
                $_SESSION['role']     = $row['role'];
                
                // Set auth cookie for session persistence on Vercel
                $token = bin2hex(random_bytes(32));
                $upd = $conn->prepare("UPDATE admin SET auth_token = ? WHERE id = ?");
                $upd->bind_param("si", $token, $row['id']);
                $upd->execute();
                $upd->close();
                setcookie('auth_token', $token, ['expires' => time() + (30 * 24 * 60 * 60), 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);

                echo "<script>window.location.href='/dashboard.php';</script>";
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
            // If password is NULL or empty, we might want to allow first-time setup or a default.
            // For now, let's assume they use their Staff ID as a default password if none is set.
            $stored_pass = $row['password'] ?: password_hash($row['staff_id'], PASSWORD_DEFAULT);
            
            if (password_verify($password, $stored_pass) || ($password === $row['staff_id'] && empty($row['password']))) {
                $_SESSION['staff_id'] = $row['staff_id'];
                $_SESSION['admin']    = $row['full_name']; // Display name
                $_SESSION['role']     = 'staff';

                echo "<script>window.location.href='/dashboard.php';</script>";
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
        --login-bg: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    }
    body.login-page {
        background: var(--login-bg);
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        font-family: 'Inter', sans-serif;
    }
    .login-container {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 40px;
        border-radius: 24px;
        width: 100%;
        max-width: 400px;
        text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }
    .login-container img { margin-bottom: 20px; filter: drop-shadow(0 0 10px rgba(255,255,255,0.2)); }
    .login-container h2 { color: white; margin-bottom: 8px; font-weight: 700; }
    .login-container p.subtitle { color: rgba(255,255,255,0.5); font-size: 14px; margin-bottom: 30px; }
    .login-container input {
        width: 100%;
        padding: 14px 20px;
        margin-bottom: 16px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        color: white;
        font-size: 15px;
        transition: all 0.3s var(--ease);
        box-sizing: border-box;
    }
    .login-container input:focus {
        background: rgba(255,255,255,0.1);
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
    }
    .login-container button {
        width: 100%;
        padding: 14px;
        background: var(--primary);
        border: none;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s var(--ease);
        margin-top: 10px;
    }
    .login-container button:hover {
        background: var(--primary-light);
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.4);
    }
    .error-msg {
        background: rgba(239, 68, 68, 0.1);
        color: #fca5a5;
        padding: 12px;
        border-radius: 8px;
        font-size: 13px;
        margin-top: 20px;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
</style>
</head>
<body class="login-page">
<div class="login-container">
    <img src="/asset/img/miss_logo.png" alt="Logo" width="80">
    <h2>Welcome Back</h2>
    <p class="subtitle">Admin or Staff? Sign in to continue.</p>
    <form method="POST" action="index.php" autocomplete="off">
        <input type="text" name="username" placeholder="Username or Staff ID" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Sign In</button>
    </form>
    <?php if (!empty($error)) echo "<p class='error-msg'>$error</p>"; ?>
</div>
</body>
</html>
