<?php

include(__DIR__ . "/../includes/config.php");

if (!isset(<?php

include(__DIR__ . "/../includes/config.php");

if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}

$success = "";
$error = "";

/* UPDATE SETTINGS */
if (isset($_POST['update_settings'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $admin_id = $_SESSION['admin_id'];

    if (empty($username)) {
        $error = "Username cannot be empty";
    } else {

        if (!empty($password)) {
            // Hash new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "UPDATE admin SET username = ?, password = ? WHERE id = ?"
            );
            $stmt->bind_param("ssi", $username, $hashedPassword, $admin_id);
        } else {
            // Update username only
            $stmt = $conn->prepare(
                "UPDATE admin SET username = ? WHERE id = ?"
            );
            $stmt->bind_param("si", $username, $admin_id);
        }

        if ($stmt->execute()) {
            $success = "Settings updated successfully";
            $_SESSION['admin'] = $username;
        } else {
            $error = "Failed to update settings";
        }

        $stmt->close();
    }
}

/* FETCH CURRENT ADMIN */
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT username FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>

<div class="settings-box">
    <h2>⚙️ Admin Settings</h2>

    <?php if ($success) { ?>
        <p class="msg-success"><?php echo $success; ?></p>
    <?php } ?>

    <?php if ($error) { ?>
        <p class="msg-error"><?php echo $error; ?></p>
    <?php } ?>

    <form method="POST">
        <label>Username</label>
        <input type="text" name="username"
               value="<?php echo htmlspecialchars($admin['username']); ?>" required>

        <label>New Password (leave blank to keep current)</label>
        <input type="password" name="password" placeholder="Enter new password">

        <button type="submit" name="update_settings">Update Settings</button>
    </form>

    <div class="back-link">
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

/* UPDATE SETTINGS */
if (isset($_POST['update_settings'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $admin_id = $_SESSION['admin_id'];

    if (empty($username)) {
        $error = "Username cannot be empty";
    } else {

        if (!empty($password)) {
            // Hash new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "UPDATE admin SET username = ?, password = ? WHERE id = ?"
            );
            $stmt->bind_param("ssi", $username, $hashedPassword, $admin_id);
        } else {
            // Update username only
            $stmt = $conn->prepare(
                "UPDATE admin SET username = ? WHERE id = ?"
            );
            $stmt->bind_param("si", $username, $admin_id);
        }

        if ($stmt->execute()) {
            $success = "Settings updated successfully";
            $_SESSION['admin'] = $username;
        } else {
            $error = "Failed to update settings";
        }

        $stmt->close();
    }
}

/* FETCH CURRENT ADMIN */
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT username FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>

<div class="settings-box">
    <h2>⚙️ Admin Settings</h2>

    <?php if ($success) { ?>
        <p class="msg-success"><?php echo $success; ?></p>
    <?php } ?>

    <?php if ($error) { ?>
        <p class="msg-error"><?php echo $error; ?></p>
    <?php } ?>

    <form method="POST">
        <label>Username</label>
        <input type="text" name="username"
               value="<?php echo htmlspecialchars($admin['username']); ?>" required>

        <label>New Password (leave blank to keep current)</label>
        <input type="password" name="password" placeholder="Enter new password">

        <button type="submit" name="update_settings">Update Settings</button>
    </form>

    <div class="back-link">
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>
</div>

</body>
</html>


