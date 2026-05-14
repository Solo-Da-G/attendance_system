<?php

include(__DIR__ . "/../includes/config.php");

// Only logged-in admin can change password
if (!isset(<?php

include(__DIR__ . "/../includes/config.php");

// Only logged-in admin can change password
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}

$success = "";
$error = "";

if (isset($_POST['change_password'])) {

    $current_password = trim($_POST['current_password']);
    $new_password     = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } else {

        $username = $_SESSION['admin'];

        // Fetch current password hash
        $stmt = $conn->prepare("SELECT password FROM `admin` WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            // Use bcrypt (password_verify) to match index.php login
            if (password_verify($current_password, $row['password'])) {

                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);

                $update = $conn->prepare(
                    "UPDATE admin SET password = ? WHERE username = ?"
                );
                $update->bind_param("ss", $new_hashed, $username);

                if ($update->execute()) {
                    $success = "Password changed successfully";
                } else {
                    $error = "Failed to update password";
                }

                $update->close();

            } else {
                $error = "Current password is incorrect";
            }
        } else {
            $error = "Admin account not found";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Change Password</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>

<div class="box">
    <h2>Change Password</h2>

    <?php if ($success) { ?>
        <p class="success"><?php echo $success; ?></p>
    <?php } ?>

    <?php if ($error) { ?>
        <p class="error"><?php echo $error; ?></p>
    <?php } ?>

    <form method="POST">
        <label>Current Password</label>
        <input type="password" name="current_password" placeholder="Enter current password" required>
        <label>New Password</label>
        <input type="password" name="new_password" placeholder="Enter new password" required>
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
        <button type="submit" name="change_password">Change Password</button>
    </form>

    <div class="back">
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>
</div>

</body>
</html>

SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$success = "";
$error = "";

if (isset($_POST['change_password'])) {

    $current_password = trim($_POST['current_password']);
    $new_password     = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } else {

        $username = $_SESSION['admin'];

        // Fetch current password hash
        $stmt = $conn->prepare("SELECT password FROM `admin` WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            // Use bcrypt (password_verify) to match index.php login
            if (password_verify($current_password, $row['password'])) {

                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);

                $update = $conn->prepare(
                    "UPDATE admin SET password = ? WHERE username = ?"
                );
                $update->bind_param("ss", $new_hashed, $username);

                if ($update->execute()) {
                    $success = "Password changed successfully";
                } else {
                    $error = "Failed to update password";
                }

                $update->close();

            } else {
                $error = "Current password is incorrect";
            }
        } else {
            $error = "Admin account not found";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Change Password</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>

<div class="box">
    <h2>Change Password</h2>

    <?php if ($success) { ?>
        <p class="success"><?php echo $success; ?></p>
    <?php } ?>

    <?php if ($error) { ?>
        <p class="error"><?php echo $error; ?></p>
    <?php } ?>

    <form method="POST">
        <label>Current Password</label>
        <input type="password" name="current_password" placeholder="Enter current password" required>
        <label>New Password</label>
        <input type="password" name="new_password" placeholder="Enter new password" required>
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
        <button type="submit" name="change_password">Change Password</button>
    </form>

    <div class="back">
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>
</div>

</body>
</html>


