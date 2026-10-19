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
<meta name="theme-color" content="#060d1f">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="TDS Attendance">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/asset/img/tds_logo.png">
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
    --primary-glow: rgba(16, 67, 159, 0.28);
    --text-main: #0f172a;
    --text-muted: #64748b;
    --border-color: #e2e8f0;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    min-height: 100vh;
    background: #060d1f;
    color: var(--text-main);
    overflow-x: hidden;
}

/* ── SECTION 1: FULL VIEWPORT HERO LANDING SHOWCASE ── */
.landing-hero {
    min-height: 100vh;
    width: 100%;
    background: linear-gradient(155deg, #020617 0%, #071538 35%, #0c2d6f 75%, #172554 100%);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 32px 40px 24px;
    position: relative;
    overflow: hidden;
    color: #ffffff;
}

/* Ambient Floating Glow Spheres */
.landing-hero::before {
    content: '';
    position: absolute;
    width: 650px; height: 650px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(37,99,235,0.32) 0%, transparent 68%);
    top: -200px; right: -200px;
    pointer-events: none;
    animation: orbPulse 10s ease-in-out infinite alternate;
}
.landing-hero::after {
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
    background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1.2px, transparent 1.2px);
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
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
}

.brand-capsule {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.24);
    border-radius: 99px;
    padding: 8px 20px 8px 12px;
    backdrop-filter: blur(14px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}

/* Enlarged TDS Logo */
.brand-logo-img {
    height: 44px;
    width: auto;
    display: block;
    background: #ffffff;
    border-radius: 12px;
    padding: 3px 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.brand-text-label {
    font-size: 15px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: 0.5px;
}

.showcase-header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.version-pill {
    background: rgba(250, 204, 21, 0.15);
    border: 1px solid rgba(250, 204, 21, 0.35);
    color: #fde047;
    font-size: 11.5px;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 99px;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}

.direct-signin-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.28);
    color: #ffffff;
    padding: 8px 18px;
    border-radius: 99px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    backdrop-filter: blur(10px);
    transition: all 0.25s ease;
}
.direct-signin-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
}

/* ── 3D SLIDER CAROUSEL SECTION ── */
.slider-container {
    position: relative;
    z-index: 5;
    margin: 20px auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-width: 900px;
    min-height: 480px;
}

.slide-track {
    position: relative;
    width: 100%;
    max-width: 580px;
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
    border-radius: 32px;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.05));
    border: 2px solid rgba(255, 255, 255, 0.28);
    backdrop-filter: blur(16px);
    box-shadow: 0 24px 50px -10px rgba(0, 0, 0, 0.5), 0 0 40px rgba(37, 99, 235, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 22px;
    overflow: hidden;
    transition: transform 0.4s ease;
}

.slide-visual-card:hover {
    transform: translateY(-4px) scale(1.03);
}

.slide-visual-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.slide-visual-card img.transparent-char {
    object-fit: contain;
    padding: 12px;
    filter: drop-shadow(0 15px 25px rgba(0,0,0,0.35));
}

.slide-badge {
    position: absolute;
    bottom: 12px;
    background: rgba(15, 23, 42, 0.88);
    border: 1px solid rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(10px);
    color: #38bdf8;
    font-size: 12px;
    font-weight: 800;
    padding: 5px 14px;
    border-radius: 99px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.3);
}

.slide-title {
    font-size: clamp(24px, 3vw, 34px);
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
    font-size: 15px;
    color: #cbd5e1;
    max-width: 480px;
    line-height: 1.6;
    font-weight: 500;
}

/* ── CAROUSEL CONTROLS & THICK MODERN ARROW BUTTONS ── */
.carousel-nav-wrap {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-top: 18px;
    z-index: 10;
}

.nav-arrow-btn {
    background: rgba(255, 255, 255, 0.14);
    border: 1.5px solid rgba(255, 255, 255, 0.28);
    color: #ffffff;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    backdrop-filter: blur(10px);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
}
.nav-arrow-btn svg {
    width: 20px;
    height: 20px;
    stroke-width: 2.8;
}
.nav-arrow-btn:hover {
    background: rgba(255, 255, 255, 0.28);
    transform: scale(1.12);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}
.nav-arrow-btn:active {
    transform: scale(0.96);
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
    background: rgba(255, 255, 255, 0.3);
    cursor: pointer;
    transition: all 0.35s ease;
}

.carousel-dot.active {
    width: 32px;
    background: linear-gradient(90deg, #38bdf8, #facc15);
    box-shadow: 0 0 14px rgba(56, 189, 248, 0.6);
}

/* ── VERTICAL MOTION CALL-TO-ACTION (LANDING TO LOGIN) ── */
.landing-slide-down-cta {
    position: relative;
    z-index: 10;
    margin: 10px auto 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    text-decoration: none;
    color: #ffffff;
    padding: 10px 24px;
    border-radius: 99px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(12px);
    transition: all 0.3s ease;
}
.landing-slide-down-cta:hover {
    background: rgba(255, 255, 255, 0.18);
    transform: translateY(3px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

.cta-row {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.3px;
}

.bounce-arrow-icon {
    width: 20px;
    height: 20px;
    stroke-width: 2.8;
    animation: bounceDown 1.8s infinite;
}
@keyframes bounceDown {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(6px); }
    60% { transform: translateY(3px); }
}

/* ── SECTION 2: LOGIN WORKSPACE ── */
.login-section {
    min-height: 100vh;
    width: 100%;
    background: radial-gradient(ellipse at 50% 30%, #0c204d 0%, #060d1f 65%, #020617 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px 80px;
    position: relative;
}

.auth-card {
    background: rgba(255, 255, 255, 0.98);
    border-radius: 26px;
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(255, 255, 255, 0.2);
    width: 100%;
    max-width: 460px;
    padding: 38px 36px;
    position: relative;
    backdrop-filter: blur(20px);
}

/* Centered Top Logo Card on Auth Card */
.auth-brand-logo-box {
    display: flex;
    justify-content: center;
    margin-bottom: 16px;
}
.auth-brand-logo-box img {
    height: 48px;
    width: auto;
    object-fit: contain;
    background: #ffffff;
    border-radius: 12px;
    padding: 4px 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

.auth-header {
    text-align: center;
    margin-bottom: 24px;
}

.auth-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eff6ff;
    color: var(--primary-light);
    font-size: 11.5px;
    font-weight: 800;
    padding: 5px 12px;
    border-radius: 99px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 8px;
}

.auth-header h2 {
    font-size: 24px;
    font-weight: 800;
    color: var(--text-main);
    letter-spacing: -0.5px;
    margin-bottom: 4px;
}

.auth-header p {
    font-size: 14px;
    color: var(--text-muted);
}

.role-hint-pill {
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 8px 14px;
    font-size: 12.5px;
    font-weight: 700;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.badge-light {
    background: #e0f2fe;
    color: #0284c7;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 6px;
    font-weight: 800;
}

.form-group {
    margin-bottom: 18px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 7px;
}

.input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.input-wrap .input-icon {
    position: absolute;
    left: 14px;
    color: #94a3b8;
    pointer-events: none;
    display: flex;
    align-items: center;
    width: 20px;
    height: 20px;
    transition: color 0.2s ease;
}
.input-wrap .input-icon svg {
    width: 100%;
    height: 100%;
    stroke-width: 2.3;
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
.toggle-password-btn svg {
    width: 20px;
    height: 20px;
    stroke-width: 2.3;
}
.toggle-password-btn:hover { color: #334155; }

.form-actions-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    margin-top: -4px;
    margin-bottom: 20px;
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
}
.btn-signin svg {
    width: 18px;
    height: 18px;
    stroke-width: 2.6;
}
.btn-signin:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px -4px rgba(16, 67, 159, 0.5);
}
.btn-signin:active { transform: translateY(1px); }

/* ── BIOMETRIC FINGERPRINT LOGIN BUTTON & DIVIDER ── */
.bio-login-separator {
    display: flex;
    align-items: center;
    margin: 20px 0 16px;
    color: #94a3b8;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}
.bio-login-separator::before,
.bio-login-separator::after {
    content: '';
    flex: 1;
    border-bottom: 1.5px solid #e2e8f0;
}
.bio-login-separator span {
    padding: 0 12px;
}

.btn-biometric-signin {
    width: 100%;
    padding: 13px 18px;
    background: #0f172a;
    border: 1.5px solid rgba(255, 255, 255, 0.12);
    border-radius: 14px;
    color: #ffffff;
    font-weight: 700;
    font-size: 14px;
    letter-spacing: 0.2px;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.2);
}
.btn-biometric-signin .bio-fp-icon {
    display: flex;
    align-items: center;
    color: #38bdf8;
}
.btn-biometric-signin:hover {
    background: #1e293b;
    border-color: #38bdf8;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(56, 189, 248, 0.25);
}
.btn-biometric-signin:active { transform: translateY(1px); }
.btn-biometric-signin:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none;
}

.biometric-feedback-box {
    margin-top: 14px;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    text-align: left;
    line-height: 1.4;
}
.biometric-feedback-box.info {
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}
.biometric-feedback-box.success {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #86efac;
}
.biometric-feedback-box.error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.notice-box {
    margin-top: 16px;
    padding: 12px 14px;
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
    margin-top: 20px;
    font-size: 12px;
    color: #94a3b8;
    text-align: center;
    line-height: 1.5;
}

.back-to-showcase-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 18px;
    color: #94a3b8;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: color 0.2s;
}
.back-to-showcase-btn:hover {
    color: #ffffff;
}

/* ── RESPONSIVE STYLES ── */
@media (max-width: 600px) {
    .landing-hero {
        padding: 24px 20px 20px;
    }
    .brand-capsule {
        padding: 6px 14px 6px 10px;
    }
    .brand-logo-img {
        height: 36px;
    }
    .brand-text-label {
        font-size: 13px;
    }
    .slide-visual-card {
        width: 190px;
        height: 190px;
        border-radius: 24px;
    }
    .slider-container {
        min-height: 390px;
    }
    .slide-track {
        height: 350px;
    }
    .slide-title {
        font-size: 22px;
    }
    .slide-desc {
        font-size: 13.5px;
    }
    .auth-card {
        padding: 30px 20px;
        border-radius: 22px;
    }
}
</style>
</head>
<body>

<!-- SECTION 1: FULL VIEWPORT HERO LANDING SHOWCASE -->
<section class="landing-hero" id="landingHero">
    <div class="mesh-overlay"></div>
    
    <!-- Top Header -->
    <header class="showcase-header">
        <div class="brand-capsule">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo" class="brand-logo-img" onerror="this.style.display='none'">
            <span class="brand-text-label">TDS ENTERPRISE</span>
        </div>
        <div class="showcase-header-right">
            <div class="version-pill">
                <span>✨ Smart Portal 2.0</span>
            </div>
            <a href="#loginSection" class="direct-signin-btn" onclick="scrollToLogin(event)">
                <span>Sign In</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </header>

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

        <!-- Carousel Navigation & Thick Modern Arrows -->
        <div class="carousel-nav-wrap">
            <button class="nav-arrow-btn" onclick="prevSlide()" aria-label="Previous Slide">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="carousel-dots" id="carouselDots">
                <div class="carousel-dot active" onclick="goToSlide(0)"></div>
                <div class="carousel-dot" onclick="goToSlide(1)"></div>
                <div class="carousel-dot" onclick="goToSlide(2)"></div>
                <div class="carousel-dot" onclick="goToSlide(3)"></div>
            </div>
            <button class="nav-arrow-btn" onclick="nextSlide()" aria-label="Next Slide">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    </div>

    <!-- Vertical Motional Slide Trigger Button -->
    <a href="#loginSection" class="landing-slide-down-cta" onclick="scrollToLogin(event)" id="slideDownTrigger">
        <div class="cta-row">
            <span>Proceed to Sign In</span>
            <svg class="bounce-arrow-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </div>
    </a>
</section>

<!-- SECTION 2: LOGIN WORKSPACE -->
<section class="login-section" id="loginSection">
    <div class="auth-card">
        <!-- Enlarged TDS Brand Logo -->
        <div class="auth-brand-logo-box">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo">
        </div>

        <div class="auth-header">
            <div class="auth-badge">🏢 Attendance Workspace</div>
            <h2>Welcome Back 👋</h2>
            <p>Please enter your credentials to sign in.</p>
        </div>

        <div class="role-hint-pill">
            <span>Staff or Administrator Access</span>
            <span class="badge-light">Secure Portal</span>
        </div>

        <form method="POST" action="index.php" autocomplete="off" id="loginForm">
            <div class="form-group">
                <label class="form-label" for="username">Username or Staff ID</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <input type="text" id="username" name="username" placeholder="e.g. TDS-001 or admin" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="passwordField">Password</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1"/></svg>
                    </span>
                    <input type="password" id="passwordField" name="password" placeholder="Enter your password" required>
                    <button type="button" class="toggle-password-btn" onclick="togglePass()" aria-label="Toggle password visibility">
                        <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="form-actions-row">
                <a href="forgot_password.php" class="forgot-password-link">Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="btn-signin" id="submitBtn">
                <span>Sign In to Dashboard</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>

        <!-- Device Biometric Fingerprint Login Option -->
        <div class="bio-login-separator">
            <span>OR AUTHENTICATE SECURELY</span>
        </div>
        <button type="button" class="btn-biometric-signin" id="btnBiometricLogin" onclick="handleBiometricLogin()">
            <span class="bio-fp-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4"/>
                    <path d="M14 13.12c0 2.38 0 6.38-1 8.88"/>
                    <path d="M2 16h.01"/>
                    <path d="M21.8 16c.2-2 .131-5.354 0-6"/>
                    <path d="M9 6.8a6 6 0 0 1 9 5.2v2"/>
                    <path d="M5.5 13.5a8 8 0 0 1 1.5-4.5"/>
                    <path d="M12 2a10 10 0 0 0-9 12"/>
                    <path d="M12 22a10 10 0 0 1-5-1.5"/>
                </svg>
            </span>
            <span>Sign In with Device Fingerprint / Sensor</span>
        </button>
        <div id="biometricFeedback" class="biometric-feedback-box" style="display:none;"></div>

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

    <a href="#landingHero" class="back-to-showcase-btn" onclick="scrollToShowcase(event)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
        <span>Back to Feature Showcase</span>
    </a>
</section>

<!-- Audio & Biometric Scripts -->
<script src="/asset/js/attendance-audio.js"></script>
<script src="/asset/js/biometric.js"></script>

<script>
// Smooth Motional Slide Controller
function scrollToLogin(e) {
    if (e && e.preventDefault) e.preventDefault();
    const target = document.getElementById('loginSection');
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function scrollToShowcase(e) {
    if (e && e.preventDefault) e.preventDefault();
    const target = document.getElementById('landingHero');
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Automated Vertical Motional Slide on Entry
window.addEventListener('load', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const hasError = document.querySelector('.notice-error');
    const isRedirect = urlParams.has('reason') || urlParams.has('logged_out') || hasError;

    // If returning from error or logout, slide down quickly; otherwise give a 1.2s showcase reveal then slide down smoothly!
    const delay = isRedirect ? 100 : 1200;
    setTimeout(() => {
        scrollToLogin();
    }, delay);
});

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

// Device Biometric / Fingerprint Login Handler
async function handleBiometricLogin() {
    const fb = document.getElementById('biometricFeedback');
    const btn = document.getElementById('btnBiometricLogin');
    const usernameInput = document.getElementById('username');
    const identifier = usernameInput ? usernameInput.value.trim() : '';

    if (fb) {
        fb.style.display = 'flex';
        fb.className = 'biometric-feedback-box info';
        fb.innerHTML = '👆 <strong>Touch your device fingerprint sensor now…</strong>';
    }
    if (btn) btn.disabled = true;

    try {
        if (!window.TDSBiometric) {
            throw new Error("Biometric module not ready. Please refresh.");
        }
        const supported = await TDSBiometric.isSupported();
        if (!supported) {
            throw new Error("Your browser or device does not have a registered fingerprint/biometric sensor.");
        }

        // 1. Get login challenge & options
        const optUrl = '/api/biometric_options.php?action=login_options' + (identifier ? '&identifier=' + encodeURIComponent(identifier) : '');
        const optRes = await fetch(optUrl);
        const options = await optRes.json();

        if (options.status !== 'success') {
            throw new Error(options.message || 'Could not start biometric authentication.');
        }

        const allowCreds = (options.allowCredentials || []).map(c => ({
            type: 'public-key',
            id: TDSBiometric.base64UrlToBuffer(c.id),
            transports: c.transports
        }));

        const getOptions = {
            challenge: TDSBiometric.base64UrlToBuffer(options.challenge),
            rpId: options.rpId,
            allowCredentials: allowCreds,
            userVerification: options.userVerification || 'required',
            timeout: options.timeout || 60000
        };

        const assertion = await navigator.credentials.get({ publicKey: getOptions });
        if (!assertion) {
            throw new Error("Biometric verification cancelled.");
        }

        // 2. Post assertion to biometric_login.php
        const payload = {
            credential_id: assertion.id,
            clientDataJSON: TDSBiometric.bufferToBase64Url(assertion.response.clientDataJSON),
            authenticatorData: TDSBiometric.bufferToBase64Url(assertion.response.authenticatorData),
            signature: TDSBiometric.bufferToBase64Url(assertion.response.signature)
        };

        const loginRes = await fetch('/api/biometric_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await loginRes.json();
        if (result.status === 'success') {
            if (window.TDSAudio) TDSAudio.playSuccess();
            if (fb) {
                fb.className = 'biometric-feedback-box success';
                fb.innerHTML = '✅ ' + result.message;
            }
            setTimeout(() => {
                window.location.href = result.redirect || 'dashboard.php';
            }, 800);
        } else {
            throw new Error(result.message || 'Biometric verification rejected.');
        }
    } catch (err) {
        if (window.TDSAudio) TDSAudio.playError();
        if (fb) {
            fb.className = 'biometric-feedback-box error';
            fb.innerHTML = '❌ ' + err.message;
        }
        if (btn) btn.disabled = false;
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

const wrapper = document.getElementById('carouselWrapper');
if (wrapper) {
    wrapper.addEventListener('mouseenter', stopAutoPlay);
    wrapper.addEventListener('mouseleave', startAutoPlay);
}

let touchStartX = 0;
let touchEndX = 0;
if (wrapper) {
    wrapper.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    wrapper.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        if (touchEndX < touchStartX - 40) {
            nextSlide();
            resetAutoPlay();
        }
        if (touchEndX > touchStartX + 40) {
            prevSlide();
            resetAutoPlay();
        }
    }, { passive: true });
}

startAutoPlay();
</script>
</body>
</html>