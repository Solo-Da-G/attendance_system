<?php
include(__DIR__ . "/../includes/config.php");

$error = "";

// DISABLE AUTO-REDIRECT FOR DEBUGGING
if (isset($_SESSION['admin_id'])) {
    echo "<h1>✅ You are logged in as: " . $_SESSION['admin'] . "</h1>";
    echo "<p><a href='/dashboard.php' style='font-size:20px; color:blue;'>Click here to go to the Dashboard</a></p>";
    echo "<p><a href='/logout.php'>Logout</a></p>";
    echo "<hr><p>Debug Info:</p>";
    echo "<pre>"; print_r($_SESSION); echo "</pre>";
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
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin']    = $row['username'];
                $_SESSION['role']     = $row['role'];
                
                session_write_close();

                echo "<h1>✅ Login Successful!</h1>";
                echo "<p><a href='/dashboard.php' style='font-size:20px; color:blue;'>Click here to go to the Dashboard</a></p>";
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
    <form method="POST" action="/api/index.php" autocomplete="off">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>
    <?php if (!empty($error)) echo "<p class='error-msg'>$error</p>"; ?>
</div>
</body>
</html>
