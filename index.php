<?php
session_start();
include("includes/config.php");

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            // Fetch staff_id if it exists
            $stmt_staff = $conn->prepare("SELECT staff_id FROM staff WHERE full_name = ?");
            $stmt_staff->bind_param("s", $row['username']);
            $stmt_staff->execute();
            $res_staff = $stmt_staff->get_result();
            if ($row_staff = $res_staff->fetch_assoc()) {
                $_SESSION['staff_id'] = $row_staff['staff_id'];
            }
            $stmt_staff->close();

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "User not found";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Attendance System</title>
<link rel="stylesheet" href="asset/css/style.css">
<style>
  body { background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; }
  .login-container { background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 350px; text-align: center; }
  h2 { margin-bottom: 20px; color: #333; }
  input { width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 5px; }
  button { width: 100%; padding: 12px; background: #28a745; color: #fff; border: none; border-radius: 5px; cursor: pointer; transition: background 0.3s; }
  button:hover { background: #218838; }
  .error { color: #dc3545; margin-bottom: 15px; font-size: 14px; }
</style>
</head>
<body>
<div class="login-container">
  <img src="asset/img/miss_logo.png" alt="Logo" width="100">
  <h2>Login</h2>
  <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
  <form method="POST">
    <input type="text" name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit" name="login">Login Now</button>
  </form>
</div>
</body>
</html>
