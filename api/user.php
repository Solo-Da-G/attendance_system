<?php
include(__DIR__ . "/../includes/config.php");

// Only logged-in users
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Only Super Admin allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    die("<h2 style='color:red;text-align:center;margin-top:100px;'>Access Denied.</h2>");
}

$message = "";

// DELETE ADMIN USER
if (isset($_GET['delete_admin_id'])) {
    $id = (int)$_GET['delete_admin_id'];
    if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == $id) {
        $message = "<p class='msg-error'>You cannot delete yourself.</p>";
    } else {
        $del = $conn->prepare("DELETE FROM `admin` WHERE id = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();
        $message = "<p class='msg-success'>Admin user deleted successfully.</p>";
    }
}

// DELETE STAFF USER
if (isset($_GET['delete_staff_id'])) {
    $staff_id = $_GET['delete_staff_id'];
    $del = $conn->prepare("DELETE FROM `staff` WHERE staff_id = ?");
    $del->bind_param("s", $staff_id);
    $del->execute();
    $del->close();
    $message = "<p class='msg-success'>Staff member deleted successfully.</p>";
}

// CREATE ADMIN USER
if (isset($_POST['create_admin_user'])) {
    $new_user = trim($_POST['new_username']);
    $new_email = trim($_POST['email']);
    $new_pass = password_hash(trim($_POST['new_password']), PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $check = $conn->prepare("SELECT id FROM `admin` WHERE username = ?");
    $check->bind_param("s", $new_user);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $message = "<p class='msg-error'>Username already exists.</p>";
    } else {
        $stmt = $conn->prepare("INSERT INTO `admin` (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $new_user, $new_email, $new_pass, $role);
        $stmt->execute();
        $stmt->close();
        $message = "<p class='msg-success'>Admin user created successfully. Default password: " . htmlspecialchars($_POST['new_password']) . "</p>";
    }
    $check->close();
}

// FETCH ADMIN USERS
$admin_users = $conn->query("SELECT id, username, email, role, status FROM `admin` ORDER BY id ASC");

// FETCH STAFF USERS
$staff_users = $conn->query("SELECT staff_id, full_name, email, phone, branch, job_title FROM `staff` ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/asset/css/style.css">
    <style>
        .user-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border); }
        .tab-btn { padding: 10px 20px; background: none; border: none; cursor: pointer; font-weight: 600; }
        .tab-btn.active { color: var(--primary); border-bottom: 2px solid var(--primary); margin-bottom: -2px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<div class="content">
    <h2>🔑 User Management</h2>
    <?php echo $message; ?>
    
    <!-- Tabs -->
    <div class="user-tabs">
        <button class="tab-btn active" onclick="showTab('admin-tab')">👑 Admin Users</button>
        <button class="tab-btn" onclick="showTab('staff-tab')">👥 Staff Members</button>
    </div>
    
    <!-- ADMIN TAB -->
    <div id="admin-tab" class="tab-content active">
        <form method="POST" style="margin-bottom:30px;">
            <h3 style="margin-bottom:16px;font-size:18px;">➕ Add New Admin User</h3>
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
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="create_admin_user" style="margin-top:12px;">Create Admin User</button>
        </form>
        
        <h3 style="margin-bottom:12px;font-size:18px;">📋 Existing Admin Users</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $admin_users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['email'] ?: '—'); ?></td>
                    <td><span class="badge <?php echo ($row['role']=='super_admin') ? 'badge-danger' : (($row['role']=='admin') ? 'badge-warning' : 'badge-info'); ?>"><?php echo $row['role']; ?></span></td>
                    <td><span class="badge badge-success"><?php echo $row['status'] ?? 'active'; ?></span></td>
                    <td>
                        <a href="edit_user.php?id=<?php echo $row['id']; ?>"><button class="action-btn edit-btn">Edit</button></a>
                        <a href="user.php?delete_admin_id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this admin user?');">
                           <button class="action-btn delete-btn">Delete</button>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <!-- STAFF TAB -->
    <div id="staff-tab" class="tab-content">
        <div style="background:#fef3c7; padding:15px; border-radius:12px; margin-bottom:20px;">
            💡 <strong>Staff Login Info:</strong> Staff members login with their <strong>Staff ID</strong> as username. 
            Default password is also their <strong>Staff ID</strong> (unless changed when created).
        </div>
        
        <a href="employees.php"><button style="margin-bottom:20px;">➕ Add New Staff Member</button></a>
        
        <h3 style="margin-bottom:12px;font-size:18px;">📋 Registered Staff Members</h3>
        <table>
            <thead>
                <tr>
                    <th>Staff ID</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Branch</th><th>Job Title</th><th>Default Pass</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $staff_users->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['staff_id']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['email'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['phone'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['branch'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['job_title'] ?: '—'); ?></td>
                    <td><button class="action-btn" style="background:#6366f1;" onclick="alert('Default password for <?php echo $row['staff_id']; ?> is: <?php echo $row['staff_id']; ?>')">🔑 Show</button></td>
                    <td>
                        <a href="edit_employee.php?id=<?php echo $row['id'] ?? 0; ?>"><button class="action-btn edit-btn">Edit</button></a>
                        <a href="user.php?delete_staff_id=<?php echo urlencode($row['staff_id']); ?>" onclick="return confirm('Delete this staff member? This will also delete their attendance records.');">
                           <button class="action-btn delete-btn">Delete</button>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($staff_users->num_rows === 0): ?>
                <tr><td colspan="8" style="text-align:center;">No staff members found. Click "Add New Staff Member" above.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="footer">
      &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Mbewu
    </div>
</div>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    event.target.classList.add('active');
}
</script>
</body>
</html>