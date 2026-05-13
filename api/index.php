<?php
session_start();
include("../includes/config.php");

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Attendance System</title>
<link rel="stylesheet" href="asset/css/style.css">
</head>

<body class="login-page">

<div class="login-container">

    <img src="./asset/img/miss_logo.png" alt="Logo" width="80">
    <h2>Admin Login</h2>

    <form method="POST" autocomplete="off">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
        <div style="text-align:right; margin-top:10px;">
            <a href="forgot_password.php" style="color:var(--primary); font-size:13px; font-weight:600; text-decoration:none;">Forgot Password?</a>
        </div>
    </form>

    <?php
    if (isset($_POST['login'])) {

        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if (empty($username) || empty($password)) {
            $error = "Please fill in both fields!";
        } else {

            // Fetch user by username
            $stmt = $conn->prepare(
                "SELECT id, username, password, role FROM admin WHERE username = ? LIMIT 1"
            );
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();

                // Verify password
                if (password_verify($password, $row['password'])) {

                    // Set sessions
                    $_SESSION['admin_id'] = $row['id'];
                    $_SESSION['admin']    = $row['username'];
                    $_SESSION['role']     = $row['role'];

                    // For geofencing: Try to find matching Staff ID
                    $s_stmt = $conn->prepare("SELECT staff_id FROM staff WHERE staff_id = ? OR full_name = ? LIMIT 1");
                    $s_stmt->bind_param("ss", $row['username'], $row['username']);
                    $s_stmt->execute();
                    $s_res = $s_stmt->get_result();
                    if ($s_row = $s_res->fetch_assoc()) {
                        $_SESSION['staff_id'] = $s_row['staff_id'];
                    }
                    $s_stmt->close();

                    header("Location: dashboard.php");
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

    if (!empty($error)) {
        echo "<p class='error-msg'>$error</p>";
    }
    ?>

</div>

</body>
</html>
