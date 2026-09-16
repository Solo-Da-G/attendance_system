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
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Sign In — TDS Attendance Management</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #10439f;
    --primary-light: #2563eb;
    --primary-dark: #0c327a;
    --primary-glow: rgba(16, 67, 159, 0.18);
    --text-main: #0f172a;
    --text-muted: #64748b;
    --bg-page: #f8fafc;
    --border-color: #e2e8f0;
}

body {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    min-height: 100vh;
    display: flex;
    background: var(--bg-page);
    color: var(--text-main);
    overflow-x: hidden;
}

/* ── LEFT SHOWCASE PANEL ── */
.panel-left {
    flex: 1.15;
    background: linear-gradient(145deg, #020617 0%, #081536 35%, #0f2c69 75%, #172554 100%);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 60px 56px;
    position: relative;
    overflow: hidden;
    min-height: 100vh;
    color: #ffffff;
}

/* Ambient Lighting Elements */
.panel-left::before {
    content: '';
    position: absolute;
    width: 600px; height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(37,99,235,0.22) 0%, transparent 68%);
    top: -180px; right: -180px;
    pointer-events: none;
    animation: orbPulse 10s ease-in-out infinite alternate;
}
.panel-left::after {
    content: '';
    position: absolute;
    width: 500px; height: 500px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(249,168,37,0.14) 0%, transparent 70%);
    bottom: -150px; left: -150px;
    pointer-events: none;
    animation: orbPulse 12s ease-in-out infinite alternate-reverse;
}
@keyframes orbPulse {
    0% { transform: scale(1); opacity: 0.7; }
    100% { transform: scale(1.12); opacity: 1; }
}

.mesh-overlay {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.06) 1.2px, transparent 1.2px);
    background-size: 26px 26px;
    pointer-events: none;
}

.showcase-header {
    position: relative;
    z-index: 2;
}

.brand-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.10);
    border: 1px solid rgba(255, 255, 255, 0.18);
    padding: 6px 16px;
    border-radius: 99px;
    color: #fde047;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    backdrop-filter: blur(10px);
}

.logo-box {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 20px;
    padding: 16px 28px;
    display: inline-flex;
    align-items: center;
    margin-top: 24px;
    backdrop-filter: blur(14px);
    box-shadow: 0 16px 36px rgba(0,0,0,0.25);
    transition: transform 0.3s ease;
}
.logo-box:hover { transform: translateY(-2px); }
.logo-box img {
    max-width: 190px;
    width: 100%;
    height: auto;
    display: block;
}

.showcase-body {
    position: relative;
    z-index: 2;
    margin: 40px 0;
    max-width: 520px;
}
.showcase-body h1 {
    font-size: clamp(28px, 3.2vw, 38px);
    font-weight: 800;
    line-height: 1.25;
    letter-spacing: -0.8px;
    margin-bottom: 16px;
}
.showcase-body h1 span.gradient-highlight {
    background: linear-gradient(135deg, #38bdf8 0%, #60a5fa 50%, #facc15 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.showcase-body p.tagline {
    color: rgba(255, 255, 255, 0.82);
    font-size: 15.5px;
    line-height: 1.6;
    font-weight: 500;
    margin-bottom: 32px;
}

.feature-grid {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.feature-card {
    display: flex;
    align-items: center;
    gap: 16px;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 16px;
    padding: 14px 18px;
    backdrop-filter: blur(10px);
    transition: all 0.25s ease;
}
.feature-card:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.25);
    transform: translateX(6px);
}
.feature-icon-wrap {
    width: 42px; height: 42px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.feature-title { font-size: 14px; font-weight: 700; color: #ffffff; }
.feature-desc { font-size: 12.5px; color: rgba(255, 255, 255, 0.7); font-weight: 500; }

.showcase-footer {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    font-size: 12.5px;
    color: rgba(255, 255, 255, 0.65);
}

/* ── RIGHT AUTH PANEL ── */
.panel-right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 32px;
    background: radial-gradient(circle at top right, #eef4ff 0%, #f8fafc 50%, #ffffff 100%);
    min-height: 100vh;
    position: relative;
}

.auth-card {
    background: #ffffff;
    border-radius: 26px;
    padding: 48px 42px;
    width: 100%;
    max-width: 450px;
    box-shadow: 0 20px 50px -12px rgba(16, 67, 159, 0.14), 0 0 0 1px rgba(16, 67, 159, 0.05);
    position: relative;
    z-index: 2;
    animation: cardAppear 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes cardAppear {
    from { opacity: 0; transform: translateY(18px); }
    to { opacity: 1; transform: translateY(0); }
}

.auth-header {
    margin-bottom: 28px;
}
.auth-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(16, 67, 159, 0.08);
    color: var(--primary);
    font-size: 11.5px;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 99px;
    margin-bottom: 12px;
    letter-spacing: 0.4px;
}
.auth-header h2 {
    color: #0f172a;
    font-size: 27px;
    font-weight: 800;
    letter-spacing: -0.6px;
    margin-bottom: 6px;
}
.auth-header p {
    color: var(--text-muted);
    font-size: 14.5px;
    font-weight: 500;
}

.role-hint-pill {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f1f5f9;
    border-radius: 12px;
    padding: 8px 12px;
    margin-bottom: 22px;
    font-size: 12px;
    color: #475569;
    font-weight: 600;
}
.role-hint-pill span.badge-light {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    color: var(--primary);
}

.form-group {
    margin-bottom: 20px;
    text-align: left;
}
.form-label {
    display: block;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 7px;
}
.input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.input-wrap .input-icon {
    position: absolute;
    left: 15px;
    color: #94a3b8;
    pointer-events: none;
    display: flex;
    align-items: center;
    font-size: 18px;
    transition: color 0.2s ease;
}
.input-wrap input {
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
}
.input-wrap input::placeholder {
    color: #94a3b8;
    font-weight: 500;
}
.input-wrap input:focus {
    background: #ffffff;
    border-color: var(--primary-light);
    outline: none;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.14);
}
.input-wrap input:focus ~ .input-icon {
    color: var(--primary-light);
}

.toggle-password-btn {
    position: absolute;
    right: 14px;
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    display: flex;
    align-items: center;
    padding: 4px;
    border-radius: 6px;
    transition: color 0.2s;
}
.toggle-password-btn:hover { color: #334155; }

.form-actions-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    margin-top: -6px;
    margin-bottom: 24px;
}
.forgot-password-link {
    font-size: 13px;
    color: var(--primary-light);
    font-weight: 700;
    text-decoration: none;
    transition: color 0.2s;
}
.forgot-password-link:hover {
    color: var(--primary);
    text-decoration: underline;
}

.btn-signin {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border: none;
    border-radius: 14px;
    color: #ffffff;
    font-weight: 800;
    font-size: 15.5px;
    letter-spacing: 0.3px;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 24px -4px rgba(16, 67, 159, 0.4);
    position: relative;
    overflow: hidden;
}
.btn-signin:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px -4px rgba(16, 67, 159, 0.5);
}
.btn-signin:active { transform: translateY(1px); }

.notice-box {
    margin-top: 20px;
    padding: 13px 16px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    text-align: left;
}
.notice-error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    animation: shake 0.35s ease both;
}
.notice-idle {
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}
@keyframes shake {
    10%, 90% { transform: translateX(-2px); }
    30%, 70% { transform: translateX(-4px); }
    50% { transform: translateX(4px); }
}

.auth-footer-help {
    margin-top: 28px;
    font-size: 12.5px;
    color: #94a3b8;
    text-align: center;
    line-height: 1.5;
}

/* ── RESPONSIVE BREAKPOINTS ── */
@media (max-width: 960px) {
    body { flex-direction: column; }
    .panel-left {
        min-height: auto;
        padding: 44px 28px;
    }
    .feature-grid { display: none; }
    .showcase-footer { display: none; }
    .panel-right {
        padding: 40px 20px 60px;
    }
}
@media (max-width: 480px) {
    .auth-card {
        padding: 32px 22px;
        border-radius: 20px;
    }
    .auth-header h2 { font-size: 23px; }
}
</style>
</head>
<body>

<!-- LEFT PANEL: Enterprise Brand Showcase -->
<div class="panel-left">
    <div class="mesh-overlay"></div>
    
    <div class="showcase-header">
        <div class="brand-badge">
            <span>✨</span> TDS Enterprise Workforce
        </div>
        <br>
        <div class="logo-box">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo" onerror="this.parentElement.innerHTML='<span style=\'color:#fff;font-weight:900;font-size:22px;letter-spacing:1px;\'>TDS HITECH</span>'">
        </div>
    </div>

    <div class="showcase-body">
        <h1>Smart Attendance &amp; <span class="gradient-highlight">Workforce Portal</span></h1>
        <p class="tagline">Streamline staff tracking with geofenced clocking, AI facial verification, and real-time attendance analytics.</p>

        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon-wrap">📍</div>
                <div>
                    <div class="feature-title">GPS Geofencing Precision</div>
                    <div class="feature-desc">Accurate branch boundary location verification</div>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrap">👤</div>
                <div>
                    <div class="feature-title">Facial Biometric Verification</div>
                    <div class="feature-desc">AI-powered anti-spoof selfie validation</div>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrap">📊</div>
                <div>
                    <div class="feature-title">Real-Time Centralized Analytics</div>
                    <div class="feature-desc">Instant punch logs, audit trails &amp; sync reports</div>
                </div>
            </div>
        </div>
    </div>

    <div class="showcase-footer">
        <span>🔒 256-Bit Encrypted &amp; FIDO2 Ready</span>
        <span>&copy; <?php echo date('Y'); ?> TDS Hitech Solutions</span>
    </div>
</div>

<!-- RIGHT PANEL: Login Form -->
<div class="panel-right">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-badge">🏢 TDS Attendance Portal</div>
            <h2>Welcome Back 👋</h2>
            <p>Please enter your credentials to sign in.</p>
        </div>

        <div class="role-hint-pill">
            <span>Staff or Administrator Login</span>
            <span class="badge-light">Secure</span>
        </div>

        <form method="POST" action="index.php" autocomplete="off" id="loginForm">
            <div class="form-group">
                <label class="form-label" for="username">Username or Staff ID</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <input type="text" id="username" name="username" placeholder="e.g. TDS-001 or admin" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="passwordField">Password</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password" id="passwordField" name="password" placeholder="Enter your password" required>
                    <button type="button" class="toggle-password-btn" onclick="togglePass()" aria-label="Toggle password visibility">
                        <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="form-actions-row">
                <a href="forgot_password.php" class="forgot-password-link">Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="btn-signin" id="submitBtn">
                <span>Sign In to Dashboard</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>

        <?php if (!empty($idle_notice)): ?>
            <div class="notice-box notice-idle">
                <span>⏱</span>
                <span><?php echo htmlspecialchars($idle_notice); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="notice-box notice-error">
                <span>⚠️</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="auth-footer-help">
            Having trouble signing in? Please contact your organization IT administrator.
        </div>
    </div>
</div>

<script>
function togglePass() {
    const field = document.getElementById('passwordField');
    const icon = document.getElementById('eyeIcon');
    if (field.type === 'password') {
        field.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
        icon.style.stroke = '#2563eb';
    } else {
        field.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        icon.style.stroke = 'currentColor';
    }
}
</script>
</body>
</html>