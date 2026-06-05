<?php
session_start();
include("includes/config.php");

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login — Attendance System</title>
    <link rel="stylesheet" href="asset/css/style.css">
    <style>
        body.login-page {
            background: radial-gradient(circle at top left, #312e81, #0f172a);
            height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            position: relative;
            width: 100%;
            max-width: 420px;
            padding: 20px;
            z-index: 10;
        }

        .auth-card {
            padding: 45px 35px;
            border-radius: 24px;
            text-align: center;
            transition: all 0.5s var(--ease);
            position: relative;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }

        .logo-area img {
            width: 90px;
            height: auto;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
        }

        .auth-card h2 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .auth-card p.subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        .input-group {
            margin-bottom: 18px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group input {
            background: rgba(255, 255, 255, 0.5) !important;
            border: 1.5px solid rgba(0,0,0,0.05);
            backdrop-filter: blur(5px);
            font-size: 15px;
            padding: 14px 16px;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .input-group input:focus {
            background: rgba(255, 255, 255, 0.9) !important;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-glow);
        }

        .btn-auth {
            width: 100%;
            padding: 15px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            margin-top: 10px;
            letter-spacing: 0.5px;
        }

        .floating-circles div {
            position: absolute;
            width: 150px;
            height: 150px;
            background: var(--primary-glow);
            border-radius: 50%;
            filter: blur(50px);
            z-index: -1;
            animation: float 10s infinite linear;
        }

        @keyframes float {
            0% { transform: translate(0, 0); }
            50% { transform: translate(50px, 30px); }
            100% { transform: translate(0, 0); }
        }

        .error-toast {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: none;
            border-left: 4px solid #ef4444;
        }
    </style>
</head>

<body class="login-page">

    <div class="floating-circles">
        <div style="top: 10%; left: 10%;"></div>
        <div style="bottom: 10%; right: 10%; background: #4f46e5; width: 250px; height: 250px;"></div>
    </div>

    <div class="login-wrapper">
        <div class="auth-card glass animate-fade-in">
            <div class="logo-area">
                <img src="./asset/img/miss_logo.png" alt="Logo">
            </div>

            <div id="errorToast" class="error-toast"></div>

            <div id="step-1" class="auth-step active">
                <h2>Welcome Back</h2>
                <p class="subtitle">Please enter your credentials to log in.</p>
                
                <form id="loginForm">
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" id="username" placeholder="Enter your staff ID or username" required autofocus autocomplete="username">
                    </div>
                    <div class="input-group">
                        <label>Password</label>
                        <div style="position: relative;">
                            <input type="password" id="password" placeholder="••••••••" required autocomplete="current-password">
                            <span id="toggle-password" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b;">
                                👁️
                            </span>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary btn-auth">Log In Now</button>
                    <div style="margin-top: 20px;">
                        <a href="forgot_password.php" style="font-size: 13px; font-weight: 600;">Forgot Password?</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const loginForm = document.getElementById('loginForm');
        const errorToast = document.getElementById('errorToast');
        
        function showError(msg) {
            errorToast.textContent = msg;
            errorToast.style.display = 'block';
            setTimeout(() => { errorToast.style.display = 'none'; }, 5000);
        }

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const originalText = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = 'Logging in...';

            const formData = new FormData();
            formData.append('action', 'verify_credentials');
            formData.append('username', document.getElementById('username').value);
            formData.append('password', document.getElementById('password').value);

            try {
                const res = await fetch('api/authenticate.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.status === 'success') {
                    btn.innerHTML = 'Success! Welcoming...';
                    setTimeout(() => window.location.href = 'dashboard.php', 800);
                } else {
                    showError(data.message);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } catch (err) {
                showError("Network error. Please try again.");
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });

        // Toggle Password
        document.getElementById('toggle-password').addEventListener('click', function() {
            const pwd = document.getElementById('password');
            const type = pwd.getAttribute('type') === 'password' ? 'text' : 'password';
            pwd.setAttribute('type', type);
            this.textContent = type === 'password' ? '👁️' : '🕶️';
        });
    </script>
</body>
</html>
