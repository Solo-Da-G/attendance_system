<?php

include(__DIR__ . "/../includes/config.php");

// Only Super Admin allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    die("<h2 style='color:var(--danger);text-align:center;margin-top:100px;font-family:Inter,sans-serif;'>Access Denied.</h2>");
}

$message = "";

// DELETE USER
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];

    if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == $id) {
        $message = "<p class='msg-error'>You cannot delete yourself.</p>";
    } else {
        $del = $conn->prepare("DELETE FROM admin WHERE id = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();
        $message = "<p class='msg-success'>User deleted successfully.</p>";
    }
}

// CREATE USER
if (isset($_POST['create_user'])) {
    $new_user = trim($_POST['new_username']);
    $new_email = trim($_POST['email']);
    $new_pass = password_hash(trim($_POST['new_password']), PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Check duplicate username or email
    $check = $conn->prepare("SELECT id FROM admin WHERE username = ? OR email = ?");
    $check->bind_param("ss", $new_user, $new_email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $message = "<p class='msg-error'>Username or Email already exists.</p>";
    } else {
        $stmt = $conn->prepare("INSERT INTO admin (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $new_user, $new_email, $new_pass, $role);
        $stmt->execute();
        $stmt->close();
        $message = "<p class='msg-success'>User created successfully.</p>";
    }
    $check->close();
}

// FETCH USERS
$users = $conn->query("SELECT id, username, email, role, status FROM admin ORDER BY id ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="asset/css/style.css">
</head>
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<div class="content">
    <h2>🔑 User Management</h2>
    <?php echo $message; ?>

    <!-- ADD USER -->
    <form method="POST" style="margin-bottom:30px;">
      <h3 style="margin-bottom:16px;font-size:18px;">Add New User</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label>Username</label>
          <input type="text" name="new_username" placeholder="Username" required>
        </div>
        <div>
          <label>Email Address</label>
          <input type="email" name="email" placeholder="Recovery Email" required>
        </div>
        <div>
          <label>Password</label>
          <input type="password" name="new_password" placeholder="Password" required>
        </div>
        <div>
          <label>Role</label>
          <select name="role" required>
              <option value="">Select Role</option>
              <option value="user">User</option>
              <option value="admin">Admin</option>
              <option value="super_admin">Super Admin</option>
          </select>
        </div>
      </div>
      <button type="submit" name="create_user" style="margin-top:12px;">Create User</button>
    </form>

    <!-- USERS TABLE -->
    <h3 style="margin-bottom:12px;font-size:18px;">Existing Users</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php while ($row = $users->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
            <td><?php echo htmlspecialchars($row['email'] ?? '—'); ?></td>
            <td><span class="badge <?php echo ($row['role']=='super_admin') ? 'badge-danger' : (($row['role']=='admin') ? 'badge-warning' : 'badge-info'); ?>"><?php echo $row['role']; ?></span></td>
            <td><span class="badge <?php echo ($row['status']=='active') ? 'badge-success' : 'badge-danger'; ?>"><?php echo $row['status'] ?? 'active'; ?></span></td>
            <td>
                <a href="edit_user.php?id=<?php echo $row['id']; ?>"><button class="action-btn edit-btn">Edit</button></a>
                <a href="user.php?delete_id=<?php echo $row['id']; ?>"
                   onclick="return confirm('Are you sure you want to delete this user?');">
                   <button class="action-btn delete-btn">Delete</button>
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

    <div class="footer">
      &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Mbewu
    </div>
</div>

</body>
</html>
