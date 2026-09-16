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
    --primary-glow: rgba(16, 67, 159, 0.25);
    --text-main: #0f172a;
    --text-muted: #64748b;
    --border-color: #e2e8f0;
}

body {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    min-height: 100vh;
    display: flex;
    background: #060d1f;
    color: var(--text-main);
    overflow-x: hidden;
}

/* ── LEFT SHOWCASE PANEL WITH 3D SLIDER ── */
.panel-left {
    flex: 1.25;
    background: linear-gradient(150deg, #020617 0%, #071538 40%, #0c2d6f 80%, #172554 100%);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 40px 48px;
    position: relative;
    overflow: hidden;
    min-height: 100vh;
    color: #ffffff;
}

/* Ambient Floating Glow Spheres */
.panel-left::before {
    content: '';
    position: absolute;
    width: 650px; height: 650px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(37,99,235,0.28) 0%, transparent 68%);
    top: -200px; right: -200px;
    pointer-events: none;
    animation: orbPulse 10s ease-in-out infinite alternate;
}
.panel-left::after {
    content: '';
    position: absolute;
    width: 550px; height: 550px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(249,168,37,0.18) 0%, transparent 70%);
    bottom: -180px; left: -180px;
    pointer-events: none;
    animation: orbPulse 12s ease-in-out infinite alternate-reverse;
}
@keyframes orbPulse {
    0% { transform: scale(1); opacity: 0.7; }
    100% { transform: scale(1.15); opacity: 1; }
}

.mesh-overlay {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.07) 1.2px, transparent 1.2px);
    background-size: 28px 28px;
    pointer-events: none;
}

.showcase-header {
    position: relative;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.brand-capsule {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 99px;
    padding: 8px 18px 8px 12px;
    backdrop-filter: blur(14px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.25);
}

.brand-logo-img {
    height: 32px;
    width: auto;
    display: block;
    background: #ffffff;
    border-radius: 99px;
    padding: 3px 8px;
}

.brand-text-label {
    font-size: 13.5px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: 0.5px;
}

.version-pill {
    background: rgba(250, 204, 21, 0.15);
    border: 1px solid rgba(250, 204, 21, 0.35);
    color: #fde047;
    font-size: 11px;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 99px;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}

/* ── 3D SLIDER CAROUSEL SECTION ── */
.slider-container {
    position: relative;
    z-index: 5;
    margin: 20px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 480px;
}

.slide-track {
    position: relative;
    width: 100%;
    max-width: 520px;
    height: 420px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.carousel-slide {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transform: translateY(20px) scale(0.96);
    transition: opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: none;
    text-align: center;
}

.carousel-slide.active {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
    z-index: 4;
}

/* Image Showcase Box */
.slide-visual-card {
    position: relative;
    width: 250px;
    height: 250px;
    border-radius: 28px;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.04));
    border: 1.5px solid rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(16px);
    box-shadow: 0 24px 50px -10px rgba(0, 0, 0, 0.45), 0 0 40px rgba(37, 99, 235, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    overflow: hidden;
    transition: transform 0.4s ease;
}

.slide-visual-card:hover {
    transform: translateY(-4px) scale(1.02);
}

.slide-visual-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.slide-visual-card img.transparent-char {
    object-fit: contain;
    padding: 10px;
    filter: drop-shadow(0 15px 25px rgba(0,0,0,0.35));
}

/* Slide Badge */
.slide-badge {
    position: absolute;
    bottom: 12px;
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(10px);
    color: #38bdf8;
    font-size: 11.5px;
    font-weight: 800;
    padding: 5px 14px;
    border-radius: 99px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.3);
}

/* Slide Text Details */
.slide-title {
    font-size: clamp(24px, 2.5vw, 30px);
    font-weight: 800;
    letter-spacing: -0.6px;
    line-height: 1.25;
    margin-bottom: 8px;
    color: #ffffff;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

.slide-title span.highlight {
    background: linear-gradient(135deg, #38bdf8 0%, #60a5fa 50%, #facc15 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.slide-desc {
    font-size: 14.5px;
    color: #cbd5e1;
    max-width: 440px;
    line-height: 1.55;
    font-weight: 500;
}

/* ── CAROUSEL CONTROLS & DOTS ── */
.carousel-nav-wrap {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 14px;
    z-index: 10;
}

.nav-arrow-btn {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    color: white;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    backdrop-filter: blur(10px);
    transition: all 0.25s ease;
}
.nav-arrow-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: scale(1.1);
}

.carousel-dots {
    display: flex;
    align-items: center;
    gap: 8px;
}

.carousel-dot {
    width: 10px;
    height: 10px;
    border-radius: 99px;
    background: rgba(255, 255, 255, 0.28);
    cursor: pointer;
    transition: all 0.35s ease;
}

.carousel-dot.active {
    width: 28px;
    background: linear-gradient(90deg, #38bdf8, #facc15);
    box-shadow: 0 0 12px rgba(56, 189, 248, 0.5);
}

.showcase-footer {
    position: relative;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 18px;
    border-top: 1px solid rgba(255, 255, 255, 0.14);
    font-size: 12.5px;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 500;
}

/* ── RIGHT AUTH PANEL ── */
.panel-right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 32px;
    background: radial-gradient(circle at top right, #f1f5f9 0%, #e2e8f0 40%, #f8fafc 100%);
    min-height: 100vh;
    position: relative;
}

.auth-card {
    background: #ffffff;
    border-radius: 28px;
    padding: 44px 40px;
    width: 100%;
    max-width: 440px;
    box-shadow: 0 24px 60px -15px rgba(16, 67, 159, 0.18), 0 0 0 1px rgba(16, 67, 159, 0.06);
    position: relative;
    z-index: 2;
    animation: cardAppear 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes cardAppear {
    from { opacity: 0; transform: translateY(18px); }
    to { opacity: 1; transform: translateY(0); }
}

.auth-header {
    margin-bottom: 24px;
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
    margin-bottom: 10px;
    letter-spacing: 0.4px;
}
.auth-header h2 {
    color: #0f172a;
    font-size: 26px;
    font-weight: 800;
    letter-spacing: -0.6px;
    margin-bottom: 4px;
}
.auth-header p {
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 500;
}

.role-hint-pill {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f1f5f9;
    border-radius: 12px;
    padding: 8px 12px;
    margin-bottom: 20px;
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
    margin-bottom: 18px;
    text-align: left;
}
.form-label {
    display: block;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 6px;
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
    padding: 13px 16px 13px 44px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    color: #0f172a;
    font-size: 14.5px;
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
    margin-top: -4px;
    margin-bottom: 22px;
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
    font-size: 15px;
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
    margin-top: 18px;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 13px;
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
    margin-top: 24px;
    font-size: 12px;
    color: #94a3b8;
    text-align: center;
    line-height: 1.5;
}

/* ── RESPONSIVE BREAKPOINTS ── */
@media (max-width: 990px) {
    body { flex-direction: column; }
    .panel-left {
        min-height: auto;
        padding: 36px 24px;
    }
    .slide-visual-card {
        width: 200px;
        height: 200px;
    }
    .slider-container {
        min-height: 380px;
    }
    .slide-track {
        height: 360px;
    }
    .showcase-footer { display: none; }
    .panel-right {
        padding: 36px 20px 60px;
    }
}
@media (max-width: 480px) {
    .auth-card {
        padding: 30px 20px;
        border-radius: 20px;
    }
    .auth-header h2 { font-size: 22px; }
    .slide-visual-card {
        width: 170px;
        height: 170px;
    }
    .slide-title { font-size: 20px; }
    .slide-desc { font-size: 13px; }
}
</style>
</head>
<body>

<!-- LEFT PANEL: Enterprise 3D Carousel Showcase -->
<div class="panel-left">
    <div class="mesh-overlay"></div>
    
    <!-- Top Header -->
    <div class="showcase-header">
        <div class="brand-capsule">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo" class="brand-logo-img" onerror="this.style.display='none'">
            <span class="brand-text-label">TDS ENTERPRISE</span>
        </div>
        <div class="version-pill">
            <span>✨ Smart Portal 2.0</span>
        </div>
    </div>

    <!-- 3D Interactive Carousel Section -->
    <div class="slider-container" id="carouselWrapper">
        <div class="slide-track">
            
            <!-- SLIDE 1: Punctuality & Time Clock Character -->
            <div class="carousel-slide active" data-slide="0">
                <div class="slide-visual-card">
                    <img src="/asset/img/cartoon_person_clock.png" alt="Smart Punctuality" class="transparent-char">
                    <div class="slide-badge">
                        <span>⏰</span> 100% On-Time Precision
                    </div>
                </div>
                <h2 class="slide-title">Smart Attendance &amp; <span class="highlight">Punctuality</span></h2>
                <p class="slide-desc">Real-time automated shift clocking with live on-time tracking, break logs, and dynamic hour calculations.</p>
            </div>

            <!-- SLIDE 2: AI Face Biometric Scanner -->
            <div class="carousel-slide" data-slide="1">
                <div class="slide-visual-card">
                    <img src="/asset/img/slide_facial_3d.jpg" alt="AI Facial Biometrics">
                    <div class="slide-badge">
                        <span>👤</span> AI Anti-Spoof Face Match
                    </div>
                </div>
                <h2 class="slide-title">AI Facial <span class="highlight">Verification</span></h2>
                <p class="slide-desc">Instant touchless attendance verification powered by intelligent 128D facial embeddings and anti-spoof checks.</p>
            </div>

            <!-- SLIDE 3: GPS Geofencing Location -->
            <div class="carousel-slide" data-slide="2">
                <div class="slide-visual-card">
                    <img src="/asset/img/slide_geofence_3d.jpg" alt="GPS Geofencing">
                    <div class="slide-badge">
                        <span>📍</span> Branch GPS Boundary
                    </div>
                </div>
                <h2 class="slide-title">GPS Branch <span class="highlight">Geofencing</span></h2>
                <p class="slide-desc">Accurate branch boundary detection ensures staff clock-in occurs exclusively within authorized office zones.</p>
            </div>

            <!-- SLIDE 4: Multi-Modal Biometrics (Fingerprint & ZKTeco) -->
            <div class="carousel-slide" data-slide="3">
                <div class="slide-visual-card">
                    <img src="/asset/img/slide_thumbprint_3d.jpg" alt="Thumbprint Biometrics">
                    <div class="slide-badge">
                        <span>🛡️</span> Multi-Device Sync
                    </div>
                </div>
                <h2 class="slide-title">Multi-Modal <span class="highlight">Biometrics</span></h2>
                <p class="slide-desc">Seamlessly clock in with Android &amp; PC thumbprints, Passkeys, and standalone ZKTeco biometric terminals.</p>
            </div>

        </div>

        <!-- Carousel Navigation & Dots -->
        <div class="carousel-nav-wrap">
            <button class="nav-arrow-btn" onclick="prevSlide()" aria-label="Previous Slide">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="carousel-dots" id="carouselDots">
                <div class="carousel-dot active" onclick="goToSlide(0)"></div>
                <div class="carousel-dot" onclick="goToSlide(1)"></div>
                <div class="carousel-dot" onclick="goToSlide(2)"></div>
                <div class="carousel-dot" onclick="goToSlide(3)"></div>
            </div>
            <button class="nav-arrow-btn" onclick="nextSlide()" aria-label="Next Slide">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    </div>

    <!-- Bottom Showcase Footer -->
    <div class="showcase-footer">
        <span>🔒 256-Bit SSL Encrypted &amp; FIDO2 Ready</span>
        <span>&copy; <?php echo date('Y'); ?> TDS Hitech Solutions</span>
    </div>
</div>

<!-- RIGHT PANEL: Login Form -->
<div class="panel-right">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-badge">🏢 Attendance Workspace</div>
            <h2>Welcome Back 👋</h2>
            <p>Please enter your credentials to sign in.</p>
        </div>

        <div class="role-hint-pill">
            <span>Staff or Administrator Login</span>
            <span class="badge-light">Secure Access</span>
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
            Having trouble signing in? Contact your system administrator for assistance.
        </div>
    </div>
</div>

<script>
// Password Visibility Toggle
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

// 3D Carousel Slider Logic
let currentSlideIndex = 0;
const slides = document.querySelectorAll('.carousel-slide');
const dots = document.querySelectorAll('.carousel-dot');
const totalSlides = slides.length;
let slideInterval = null;

function showSlide(index) {
    if (index < 0) index = totalSlides - 1;
    if (index >= totalSlides) index = 0;
    currentSlideIndex = index;

    slides.forEach((slide, idx) => {
        slide.classList.toggle('active', idx === currentSlideIndex);
    });

    dots.forEach((dot, idx) => {
        dot.classList.toggle('active', idx === currentSlideIndex);
    });
}

function nextSlide() {
    showSlide(currentSlideIndex + 1);
}

function prevSlide() {
    showSlide(currentSlideIndex - 1);
}

function goToSlide(index) {
    showSlide(index);
    resetAutoPlay();
}

function startAutoPlay() {
    slideInterval = setInterval(nextSlide, 4800);
}

function stopAutoPlay() {
    if (slideInterval) clearInterval(slideInterval);
}

function resetAutoPlay() {
    stopAutoPlay();
    startAutoPlay();
}

// Event Listeners for Hover and Auto-Play
const wrapper = document.getElementById('carouselWrapper');
if (wrapper) {
    wrapper.addEventListener('mouseenter', stopAutoPlay);
    wrapper.addEventListener('mouseleave', startAutoPlay);
}

// Touch swipe support for mobile
let touchStartX = 0;
let touchEndX = 0;
if (wrapper) {
    wrapper.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    wrapper.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, { passive: true });
}

function handleSwipe() {
    if (touchEndX < touchStartX - 40) {
        nextSlide();
        resetAutoPlay();
    }
    if (touchEndX > touchStartX + 40) {
        prevSlide();
        resetAutoPlay();
    }
}

// Start rotation on page load
startAutoPlay();
</script>
</body>
</html>