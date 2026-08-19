<?php
include(__DIR__ . "/includes/config.php");

$error = "";
$idle_notice = (isset($_GET['reason']) && $_GET['reason'] === 'idle')
    ? 'You were signed out after a period of inactivity.'
    : '';

if (isset($_SESSION['admin_id']) || isset($_SESSION['staff_id'])) {
    header("Location: /dashboard.php");
    exit;
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please fill in both fields!";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM `admin` WHERE (username = ? OR email = ?) LIMIT 1");
        if (!$stmt) { die("MySQL Prepare Error (Admin Table): " . $conn->error); }
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
                    if ($upd) { $upd->bind_param("si", $token, $row['id']); $saved = (bool)$upd->execute(); $upd->close(); }
                }
                if (!$saved) {
                    $error = "Server setup incomplete (admin auth token not saved). Please run api/fix_database.php.";
                    goto render_form;
                } else {
                    $secure = function_exists('is_https_request') ? is_https_request() : true;
                    setcookie('auth_token', $token, ['expires' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
                    header("Location: /dashboard.php");
                    exit;
                }
            }
        }

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
                if ($password === $row['staff_id'] && (empty($row['password']) || password_verify($row['staff_id'], $row['password']))) {
                    $_SESSION['require_password_change'] = true;
                }
                $token = bin2hex(random_bytes(32));
                $saved = false;
                if (function_exists('db_has_column') && db_has_column($conn, 'staff', 'auth_token')) {
                    $upd = $conn->prepare("UPDATE `staff` SET auth_token = ? WHERE id = ?");
                    if ($upd) { $upd->bind_param("si", $token, $row['id']); $saved = (bool)$upd->execute(); $upd->close(); }
                }
                if (!$saved) {
                    $error = "Server setup incomplete (staff auth token not saved). Please run api/fix_database.php.";
                    goto render_form;
                } else {
                    $secure = function_exists('is_https_request') ? is_https_request() : true;
                    setcookie('auth_token', 'staff_' . $token, ['expires' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
                    header("Location: /dashboard.php");
                    exit;
                }
            }
        }

        if (empty($error)) { $error = "Invalid username or password!"; }
    }
}
render_form:
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — TDS Attendance System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #10439f;
    --primary-light: #2563eb;
    --primary-glow: rgba(16, 67, 159, 0.18);
    --accent-red: #e53935;
    --accent-yellow: #f9a825;
}

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    min-height: 100vh;
    display: flex;
    background: #f0f4ff;
}

/* ── LEFT PANEL ── */
.panel-left {
    flex: 1.1;
    background: linear-gradient(145deg, #0a1f5c 0%, var(--primary) 50%, #1e3a8a 100%);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 60px 50px;
    position: relative;
    overflow: hidden;
    min-height: 100vh;
}

/* Animated background circles */
.panel-left::before {
    content: '';
    position: absolute;
    width: 500px; height: 500px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(249,168,37,0.25) 0%, transparent 70%);
    top: -150px; right: -150px;
    animation: pulse 8s ease-in-out infinite;
}
.panel-left::after {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(37,99,235,0.3) 0%, transparent 70%);
    bottom: -100px; left: -100px;
    animation: pulse 10s ease-in-out infinite reverse;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.8; }
    50%       { transform: scale(1.15); opacity: 1; }
}

.panel-left .brand-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 400px;
}
.panel-left .logo-wrap {
    background: rgba(255,255,255,0.10);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 20px;
    padding: 20px 32px;
    display: inline-block;
    margin-bottom: 40px;
    backdrop-filter: blur(8px);
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}
.panel-left .logo-wrap img {
    max-width: 200px;
    width: 100%;
    filter: brightness(0) invert(1);
}

.panel-left h1 {
    color: #ffffff;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.3;
    margin-bottom: 16px;
    letter-spacing: -0.5px;
}
.panel-left h1 span {
    color: var(--accent-yellow);
}
.panel-left p.tagline {
    color: rgba(255,255,255,0.75);
    font-size: 15.5px;
    font-weight: 500;
    line-height: 1.7;
    margin-bottom: 44px;
}

/* Feature bullets */
.features {
    display: flex;
    flex-direction: column;
    gap: 16px;
    text-align: left;
}
.feature-item {
    display: flex;
    align-items: center;
    gap: 14px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 14px;
    padding: 14px 18px;
    backdrop-filter: blur(6px);
    transition: background 0.3s;
}
.feature-item:hover {
    background: rgba(255,255,255,0.14);
}
.feature-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.feature-icon.blue  { background: rgba(37,99,235,0.35); }
.feature-icon.red   { background: rgba(229,57,53,0.35); }
.feature-icon.gold  { background: rgba(249,168,37,0.35); }
.feature-text strong {
    display: block;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
}
.feature-text span {
    color: rgba(255,255,255,0.6);
    font-size: 13px;
}

/* Wave bottom decoration */
.panel-left .wave {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 80px;
    background: rgba(255,255,255,0.04);
    clip-path: ellipse(110% 100% at 50% 100%);
}

/* ── RIGHT PANEL ── */
.panel-right {
    flex: 0.9;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 40px;
    background: #f8faff;
    min-height: 100vh;
}

.login-card {
    background: #ffffff;
    border-radius: 24px;
    padding: 44px 40px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 10px 50px -12px rgba(16, 67, 159, 0.15), 0 0 0 1px rgba(16,67,159,0.06);
    animation: slideIn 0.6s cubic-bezier(0.4, 0, 0.2, 1) both;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateX(20px); }
    to   { opacity: 1; transform: translateX(0); }
}

.login-card .card-header {
    margin-bottom: 32px;
}
.login-card .card-header h2 {
    color: #0f172a;
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}
.login-card .card-header p {
    color: #64748b;
    font-size: 14.5px;
    font-weight: 500;
}
.login-card .card-header .greeting-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(16, 67, 159, 0.07);
    color: var(--primary);
    font-size: 12.5px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 20px;
    margin-bottom: 14px;
    letter-spacing: 0.3px;
}

.input-label {
    display: block;
    color: #374151;
    font-size: 13.5px;
    font-weight: 700;
    margin-bottom: 8px;
}
.input-group {
    position: relative;
    margin-bottom: 20px;
}
.input-group input {
    width: 100%;
    padding: 14px 16px 14px 44px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    color: #0f172a;
    font-size: 15px;
    font-weight: 500;
    font-family: inherit;
    transition: all 0.25s ease;
    display: block;
}
.input-group input::placeholder { color: #94a3b8; }
.input-group input:focus {
    background: #fff;
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 4px var(--primary-glow);
}
.input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
    font-size: 18px;
    display: flex; align-items: center;
}
.toggle-password {
    position: absolute;
    right: 14px; top: 50%;
    transform: translateY(-50%);
    cursor: pointer; opacity: 0.55;
    transition: opacity 0.2s;
    display: flex; align-items: center;
}
.toggle-password:hover { opacity: 1; }
.toggle-password svg { width: 20px; height: 20px; fill: #64748b; }

input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus {
    -webkit-text-fill-color: #0f172a !important;
    -webkit-box-shadow: 0 0 0px 1000px #f8fafc inset !important;
}

.form-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: -12px;
    margin-bottom: 24px;
}
.forgot-pass {
    font-size: 13px;
    color: var(--primary);
    font-weight: 700;
    text-decoration: none;
    transition: color 0.2s;
}
.forgot-pass:hover { color: var(--primary-light); text-decoration: underline; }

.btn-login {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border: none;
    border-radius: 12px;
    color: #fff;
    font-weight: 800;
    font-size: 15.5px;
    letter-spacing: 0.4px;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.3s ease;
    box-shadow: 0 6px 20px -4px rgba(16, 67, 159, 0.45);
    position: relative; overflow: hidden;
}
.btn-login::after {
    content: '';
    position: absolute;
    top: 0; left: -100%; width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
    transition: left 0.5s ease;
}
.btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 28px -4px rgba(16, 67, 159, 0.5); }
.btn-login:hover::after { left: 100%; }
.btn-login:active { transform: translateY(1px); }

.error-msg {
    background: rgba(239, 68, 68, 0.08);
    color: #b91c1c;
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 600;
    margin-top: 20px;
    border: 1px solid rgba(239, 68, 68, 0.16);
    animation: shake 0.4s ease both;
}
@keyframes shake {
    10%, 90% { transform: translateX(-2px); }
    20%, 80% { transform: translateX(3px); }
    30%, 50%, 70% { transform: translateX(-5px); }
    40%, 60% { transform: translateX(5px); }
}

/* Responsive */
@media (max-width: 768px) {
    body { flex-direction: column; }
    .panel-left { min-height: auto; padding: 40px 24px; }
    .panel-left .features { display: none; }
    .panel-right { padding: 30px 20px; }
    .login-card { padding: 32px 24px; }
}
</style>
</head>
<body>

<!-- LEFT PANEL: Branding -->
<div class="panel-left">
    <div class="brand-content">
        <div class="logo-wrap">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo">
        </div>
        <h1>Smart Attendance <span>Management</span> System</h1>
        <p class="tagline">Track, manage, and analyse your workforce attendance with real-time precision and enterprise-grade security.</p>

        <div class="features">
            <div class="feature-item">
                <div class="feature-icon blue">📍</div>
                <div class="feature-text">
                    <strong>GPS Geo-fencing</strong>
                    <span>Location-based clock-in accuracy</span>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon gold">📊</div>
                <div class="feature-text">
                    <strong>Live Reports & Analytics</strong>
                    <span>Instant attendance insights & exports</span>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon red">🔒</div>
                <div class="feature-text">
                    <strong>Role-Based Access</strong>
                    <span>Admin and staff security layers</span>
                </div>
            </div>
        </div>
    </div>
    <div class="wave"></div>
</div>

<!-- RIGHT PANEL: Login Form -->
<div class="panel-right">
    <div class="login-card">
        <div class="card-header">
            <div class="greeting-badge">🏢 TDS HITECH SOLUTIONS</div>
            <h2>Welcome Back 👋</h2>
            <p>Sign in to access your dashboard</p>
        </div>

        <form method="POST" action="index.php" autocomplete="off">
            <label class="input-label" for="username">Username or Staff ID</label>
            <div class="input-group">
                <span class="input-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <input type="text" id="username" name="username" placeholder="Enter username or staff ID" required>
            </div>

            <label class="input-label" for="passwordField">Password</label>
            <div class="input-group">
                <span class="input-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
                <input type="password" id="passwordField" name="password" placeholder="Enter your password" required>
                <div class="toggle-password" onclick="togglePass()">
                    <svg id="eyeIcon" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                </div>
            </div>

            <div class="form-footer">
                <a href="forgot_password.php" class="forgot-pass">Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="btn-login">Sign In →</button>
        </form>

        <?php if (!empty($idle_notice)) echo "<p class='error-msg' style='background:rgba(59,130,246,0.08);color:#1e40af;border-color:rgba(59,130,246,0.16);'>⏱ $idle_notice</p>"; ?>
        <?php if (!empty($error)) echo "<p class='error-msg'>⚠ $error</p>"; ?>
    </div>
</div>

<script>
function togglePass() {
    const field = document.getElementById('passwordField');
    const icon = document.getElementById('eyeIcon');
    if (field.type === 'password') {
        field.type = 'text';
        icon.style.fill = '#2563eb';
    } else {
        field.type = 'password';
        icon.style.fill = '#64748b';
    }
}
</script>
</body>
</html>