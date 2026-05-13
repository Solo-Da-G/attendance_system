<?php
session_start();
include(__DIR__ . "/../includes/config.php");

// Only super admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    die("<h2 style='color:var(--danger);text-align:center;margin-top:100px;'>Access Denied.</h2>");
}

// Validate ID
if (!isset($_GET['id'])) {
    header("Location: user.php");
    exit;
}

$id = (int)$_GET['id'];
$message = "";

// Fetch user
$stmt = $conn->prepare("SELECT username, email, role FROM admin WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found");
}

$user = $result->fetch_assoc();
$stmt->close();

// Update user
if (isset($_POST['update_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $update = $conn->prepare(
            "UPDATE admin SET username = ?, email = ?, password = ?, role = ? WHERE id = ?"
        );
        $update->bind_param("ssssi", $username, $email, $password, $role, $id);
    } else {
        $update = $conn->prepare(
            "UPDATE admin SET username = ?, email = ?, role = ? WHERE id = ?"
        );
        $update->bind_param("sssi", $username, $email, $role, $id);
    }

    if ($update->execute()) {
        $message = "User updated successfully";
        // Refresh user data
        $user['username'] = $username;
        $user['email'] = $email;
        $user['role'] = $role;
    }
    $update->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="asset/css/style.css">
</head>
<body>

<div class="edit-container">
    <h2>Edit User</h2>
    <?php if ($message): ?>
      <p class="msg-success"><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Username</label>
        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

        <label>Email Address</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>

        <label>New Password (leave blank to keep old)</label>
        <input type="password" name="password" placeholder="Enter new password">

        <label>Role</label>
        <select name="role">
            <option value="user" <?php if ($user['role']=='user') echo 'selected'; ?>>User</option>
            <option value="admin" <?php if ($user['role']=='admin') echo 'selected'; ?>>Admin</option>
            <option value="super_admin" <?php if ($user['role']=='super_admin') echo 'selected'; ?>>Super Admin</option>
        </select>

        <div style="display:flex;gap:12px;margin-top:10px;">
          <button type="submit" name="update_user">Update User</button>
          <a href="user.php" class="cancel-btn">← Back</a>
        </div>
    </form>
</div>

</body>
</html>
