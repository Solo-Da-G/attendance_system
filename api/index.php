<?php
include(__DIR__ . "/../includes/config.php");

$error = "";

// Add auth_token column if it doesn't exist (safe to run multiple times)
$conn->query("ALTER TABLE admin ADD COLUMN IF NOT EXISTS auth_token VARCHAR(64) DEFAULT NULL");

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    echo "<script>window.location.href='/dashboard.php';</script>";
    exit;
}

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

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {

                // Generate a unique token and store in DB
                $token = bin2hex(random_bytes(32));
                $conn->prepare("UPDATE admin SET auth_token = ? WHERE id = ?")->execute();
                $upd = $conn->prepare("UPDATE admin SET auth_token = ? WHERE id = ?");
                $upd->bind_param("si", $token, $row['id']);
                $upd->execute();

                // Set session
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin']    = $row['username'];
                $_SESSION['role']     = $row['role'];

                // Set a long-lived cookie (30 days) that works across all Vercel instances
                setcookie('auth_token', $token, [
                    'expires'  => time() + (30 * 24 * 60 * 60),
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                $stmt->close();
                echo "<script>window.location.href='/dashboard.php';</script>";
                exit;
            } else {
                $error = "Invalid username or password!";
            }
        } else {
            $error = "Invalid username or password!";
        }
        $stmt->close();
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
