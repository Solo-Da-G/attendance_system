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

/* ── LEFT PANEL: Executive Luxury Dark Theme ── */
.panel-left {
    flex: 1.2;
    background: linear-gradient(140deg, #020617 0%, #081536 40%, #0f2c69 75%, #1e1b4b 100%);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 60px 48px;
    position: relative;
    overflow: hidden;
    min-height: 100vh;
}

/* Background ambient light Orbs */
.panel-left::before {
    content: '';
    position: absolute;
    width: 650px; height: 650px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(249,168,37,0.20) 0%, transparent 68%);
    top: -220px; right: -220px;
    animation: pulseOrb 9s ease-in-out infinite;
    pointer-events: none;
}
.panel-left::after {
    content: '';
    position: absolute;
    width: 550px; height: 550px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(37,99,235,0.25) 0%, transparent 70%);
    bottom: -180px; left: -180px;
    animation: pulseOrb 12s ease-in-out infinite reverse;
    pointer-events: none;
}

.mesh-grid-bg {
    position: absolute;
    inset: 0;
    background-image: 
        radial-gradient(rgba(255, 255, 255, 0.07) 1.2px, transparent 1.2px);
    background-size: 28px 28px;
    opacity: 0.75;
    pointer-events: none;
}

@keyframes pulseOrb {
    0%, 100% { transform: scale(1); opacity: 0.8; }
    50%       { transform: scale(1.15); opacity: 1; }
}

.panel-left .brand-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 440px;
    width: 100%;
}

.enterprise-top-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.10);
    border: 1px solid rgba(255, 255, 255, 0.20);
    padding: 7px 18px;
    border-radius: 99px;
    color: #fde047;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    margin-bottom: 24px;
    backdrop-filter: blur(12px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}

.panel-left .logo-wrap {
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 24px;
    padding: 22px 38px;
    display: inline-block;
    margin-bottom: 32px;
    backdrop-filter: blur(16px);
    box-shadow: 0 16px 40px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.35);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.panel-left .logo-wrap:hover {
    transform: translateY(-4px);
    box-shadow: 0 22px 48px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.45);
}
.panel-left .logo-wrap img {
    max-width: 215px;
    width: 100%;
    display: block;
    filter: drop-shadow(0 4px 12px rgba(0,0,0,0.35));
}

.panel-left h1 {
    color: #ffffff;
    font-size: 32px;
    font-weight: 800;
    line-height: 1.25;
    margin-bottom: 16px;
    letter-spacing: -0.6px;
}
.panel-left h1 span.gradient-text {
    background: linear-gradient(135deg, #38bdf8 0%, #fbbf24 50%, #f59e0b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.panel-left p.tagline {
    color: rgba(255,255,255,0.85);
    font-size: 15px;
    font-weight: 500;
    line-height: 1.65;
    margin-bottom: 34px;
}

/* Feature Bullet Cards */
.features {
    display: flex;
    flex-direction: column;
    gap: 14px;
    text-align: left;
    margin-bottom: 34px;
}
.feature-item {
    display: flex;
    align-items: center;
    gap: 16px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 18px;
    padding: 15px 20px;
    backdrop-filter: blur(12px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    transition: transform 0.25s ease, background 0.25s ease, border-color 0.25s ease;
}
.feature-item:hover {
    background: rgba(255,255,255,0.14);
    border-color: rgba(255,255,255,0.30);
    transform: translateX(6px);
}
.feature-icon {
    width: 46px; height: 46px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(0,0,0,0.25);
    border: 1px solid rgba(255,255,255,0.25);
}
.feature-icon.blue  { background: linear-gradient(135deg, rgba(37,99,235,0.5), rgba(59,130,246,0.3)); }
.feature-icon.gold  { background: linear-gradient(135deg, rgba(245,158,11,0.5), rgba(251,191,36,0.3)); }
.feature-icon.red   { background: linear-gradient(135deg, rgba(239,68,68,0.5), rgba(244,63,94,0.3)); }

.feature-text strong {
    display: block;
    color: #ffffff;
    font-size: 14.5px;
    font-weight: 700;
    margin-bottom: 2px;
}
.feature-text span {
    color: rgba(255,255,255,0.72);
    font-size: 13px;
    font-weight: 500;
}

/* Trust Ribbon Bar */
.trust-ribbon {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 18px;
    padding: 16px 14px;
    backdrop-filter: blur(12px);
    width: 100%;
}
.trust-stat {
    text-align: center;
}
.trust-stat-val {
    font-size: 16.5px;
    font-weight: 800;
    color: #fde047;
    line-height: 1.2;
}
.trust-stat-lbl {
    font-size: 10.5px;
    color: rgba(255,255,255,0.75);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 3px;
}

/* ── RIGHT PANEL: Integrated Login Card with Top-Right Mascot ── */
.panel-right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 36px;
    background: radial-gradient(circle at 80% 20%, #eef2ff 0%, #f8faff 60%, #f1f5f9 100%);
    min-height: 100vh;
    position: relative;
    overflow: hidden;
}

.login-card-wrapper {
    position: relative;
    width: 100%;
    max-width: 440px;
    margin-top: 40px;
}

/* TOP-RIGHT MASCOT CHARACTER HEADER */
.top-right-mascot {
    position: absolute;
    top: -80px;
    right: -20px;
    width: 170px;
    z-index: 10;
    filter: drop-shadow(0 15px 25px rgba(16, 67, 159, 0.22));
    animation: floatMascot 4s ease-in-out infinite;
    pointer-events: none;
}

@keyframes floatMascot {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

.top-right-mascot img {
    width: 100%;
    height: auto;
    display: block;
}

.mascot-tag-badge {
    position: absolute;
    top: 10px;
    left: -70px;
    background: #ffffff;
    border: 1px solid rgba(16, 67, 159, 0.18);
    color: #10439f;
    font-size: 11px;
    font-weight: 800;
    padding: 5px 12px;
    border-radius: 99px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    white-space: nowrap;
    animation: badgePulse 3s ease-in-out infinite alternate;
}

@keyframes badgePulse {
    0% { transform: scale(1); }
    100% { transform: scale(1.05); }
}

.login-card {
    background: #ffffff;
    border-radius: 28px;
    padding: 44px 38px;
    width: 100%;
    box-shadow: 0 20px 60px -15px rgba(16, 67, 159, 0.16), 0 0 0 1px rgba(16,67,159,0.06);
    position: relative;
    z-index: 2;
    animation: slideIn 0.6s cubic-bezier(0.4, 0, 0.2, 1) both;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

.login-card .card-header {
    margin-bottom: 30px;
    padding-right: 80px; /* Space for top-right mascot overlap */
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
    font-size: 14px;
    font-weight: 500;
}
.login-card .card-header .greeting-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(16, 67, 159, 0.08);
    color: var(--primary);
    font-size: 12px;
    font-weight: 800;
    padding: 5px 14px;
    border-radius: 20px;
    margin-bottom: 12px;
    letter-spacing: 0.3px;
    border: 1px solid rgba(16, 67, 159, 0.12);
}

.input-label {
    display: block;
    color: #334155;
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
    border-radius: 14px;
    color: #0f172a;
    font-size: 15px;
    font-weight: 600;
    font-family: inherit;
    transition: all 0.25s ease;
    display: block;
}
.input-group input::placeholder { color: #94a3b8; font-weight: 500; }
.input-group input:focus {
    background: #fff;
    border-color: var(--primary-light);
    outline: none;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
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

.form-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: -8px;
    margin-bottom: 24px;
}
.forgot-pass {
    font-size: 13.5px;
    color: var(--primary-light);
    font-weight: 700;
    text-decoration: none;
    transition: color 0.2s;
}
.forgot-pass:hover { color: var(--primary); text-decoration: underline; }

.btn-login {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border: none;
    border-radius: 14px;
    color: #fff;
    font-weight: 800;
    font-size: 15.5px;
    letter-spacing: 0.4px;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.3s ease;
    box-shadow: 0 8px 24px -4px rgba(16, 67, 159, 0.4);
    position: relative; overflow: hidden;
}
.btn-login::after {
    content: '';
    position: absolute;
    top: 0; left: -100%; width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}
.btn-login:hover { transform: translateY(-2px); box-shadow: 0 12px 30px -4px rgba(16, 67, 159, 0.5); }
.btn-login:hover::after { left: 100%; }
.btn-login:active { transform: translateY(1px); }

.error-msg {
    background: rgba(239, 68, 68, 0.08);
    color: #b91c1c;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    margin-top: 20px;
    border: 1px solid rgba(239, 68, 68, 0.18);
    animation: shake 0.4s ease both;
}
@keyframes shake {
    10%, 90% { transform: translateX(-2px); }
    20%, 80% { transform: translateX(3px); }
    30%, 50%, 70% { transform: translateX(-5px); }
    40%, 60% { transform: translateX(5px); }
}

/* Responsive Rules */
@media (max-width: 992px) {
    body { flex-direction: column; }
    .panel-left { min-height: auto; padding: 48px 24px; }
    .panel-left .features { gap: 12px; }
    .panel-right { padding: 80px 20px 40px; }
    .top-right-mascot { top: -70px; right: 10px; width: 140px; }
}

@media (max-width: 576px) {
    .panel-left .features { display: none; }
    .login-card { padding: 32px 24px; }
    .login-card .card-header { padding-right: 0; }
    .top-right-mascot { top: -65px; right: 0; width: 120px; }
    .mascot-tag-badge { display: none; }
}
</style>
</head>
<body>

<!-- LEFT PANEL: Branding & Features -->
<div class="panel-left">
    <div class="mesh-grid-bg"></div>
    <div class="brand-content">
        <div class="enterprise-top-badge">
            <span>✨</span> TDS Enterprise Attendance System
        </div>
        <br>
        <div class="logo-wrap">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo" onerror="this.parentElement.innerHTML='<div style=\'color:white;font-size:24px;font-weight:900;letter-spacing:1.5px;\'>TDS HITECH</div>'">
        </div>
        <h1>Smart Attendance <span class="gradient-text">Management</span> System</h1>
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

        <div class="trust-ribbon">
            <div class="trust-stat">
                <div class="trust-stat-val">99.9%</div>
                <div class="trust-stat-lbl">Accuracy</div>
            </div>
            <div class="trust-stat">
                <div class="trust-stat-val">Real-Time</div>
                <div class="trust-stat-lbl">GPS & Face</div>
            </div>
            <div class="trust-stat">
                <div class="trust-stat-val">256-bit</div>
                <div class="trust-stat-lbl">Encrypted</div>
            </div>
        </div>
    </div>
</div>

<!-- RIGHT PANEL: Login Form with Top Right Mascot -->
<div class="panel-right">
    <div class="login-card-wrapper">
        <!-- 3D Mascot Character positioned at top-right side -->
        <div class="top-right-mascot">
            <div class="mascot-tag-badge">⏱️ Always On Time</div>
            <img src="/asset/img/cartoon_person_clock.png" alt="Smart Attendance Mascot Character">
        </div>

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