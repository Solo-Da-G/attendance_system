<?php
include(__DIR__ . "/../includes/config.php");

$error = "";

// If already logged in, go to dashboard
if (isset($_SESSION['admin_id'])) {
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
        $stmt = $conn->prepare("SELECT id, username, password, role FROM admin WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if (password_verify($password, $row['password'])) {
                // Set session values
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin']    = $row['username'];
                $_SESSION['role']     = $row['role'];

                // Generate and store a unique auth token in the database
                $token = bin2hex(random_bytes(32));
                $upd = $conn->prepare("UPDATE admin SET auth_token = ? WHERE id = ?");
                $upd->bind_param("si", $token, $row['id']);
                $upd->execute();
                $upd->close();

                // Set a 30-day browser cookie — works across all Vercel serverless instances
                setcookie('auth_token', $token, [
                    'expires'  => time() + (30 * 24 * 60 * 60),
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                echo "<script>window.location.href='/dashboard.php';</script>";
                exit;
            } else {
                $error = "Invalid username or password!";
            }
        } else {
            $error = "Invalid username or password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Attendance System</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body class="login-page">
<div class="login-container">
    <img src="/asset/img/miss_logo.png" alt="Logo" width="80">
    <h2>Admin Login</h2>
    <form method="POST" action="index.php" autocomplete="off">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>
    <?php if (!empty($error)) echo "<p class='error-msg'>$error</p>"; ?>
</div>
</body>
</html>
