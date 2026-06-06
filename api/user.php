<?php
include(__DIR__ . "/includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

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
        // Prevent normal admin from deleting super_admin
        $check_role = $conn->query("SELECT role FROM `admin` WHERE id = $id")->fetch_assoc();
        if ($check_role && $check_role['role'] === 'super_admin' && $_SESSION['role'] !== 'super_admin') {
            $message = "<p class='msg-error'>Access Denied: Admins cannot delete Super Admins.</p>";
        } else {
            $del = $conn->prepare("UPDATE `admin` SET deleted_at = NOW() WHERE id = ?");
            $del->bind_param("i", $id);
            $del->execute();
            $del->close();
            $message = "<p class='msg-success'>Admin user moved to Recycle Bin.</p>";
        }
    }
}

if (isset($_GET['delete_staff_id'])) {
    $staff_id = $_GET['delete_staff_id'];
    $del = $conn->prepare("UPDATE `staff` SET deleted_at = NOW() WHERE staff_id = ?");
    $del->bind_param("s", $staff_id);
    $del->execute();
    $del->close();
    $message = "<p class='msg-success'>Staff member moved to Recycle Bin.</p>";
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
        $message = "<p class='msg-success'>Admin user created successfully.</p>";
    }
    $check->close();
}

$admin_users = $conn->query("SELECT id, username, email, role, status FROM `admin` WHERE deleted_at IS NULL ORDER BY id ASC");
$staff_users = $conn->query("SELECT id, staff_id, full_name, email, phone, branch FROM `staff` WHERE deleted_at IS NULL ORDER BY id DESC");
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
<body class="app-page users-page">

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
    <h2>🔑 User Management</h2>
    <?php echo $message; ?>
    
    <div class="user-tabs">
        <button class="tab-btn active" onclick="showTab('admin-tab')">👤 Admin Users</button>
        <button class="tab-btn" onclick="showTab('staff-tab')">👥 Staff Members</button>
    </div>
    
    <div id="admin-tab" class="tab-content active">
        <form method="POST" style="margin-bottom:30px;">
            <h3 style="margin-bottom:16px;font-size:18px;">âž• Add New Admin User</h3>
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
        
        <div class="search-section" style="margin: 0 0 14px;">
            <span class="search-icon">🔍</span>
            <input type="text" id="adminUserSearch" class="search-input" placeholder="Search admin users (username/email/role)..." onkeyup="filterTable('adminUserSearch','adminUsersTable')">
        </div>

        <h3 style="margin-bottom:12px;font-size:18px;">📋 Existing Admin Users</h3>
        <div class="table-card">
        <div style="overflow:auto;max-width:100%;">
        <table class="responsive-table" id="adminUsersTable">
            <thead>
                <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $admin_users->fetch_assoc()): ?>
                <tr>
                    <td data-label="ID"><?php echo $row['id']; ?></td>
                    <td data-label="Username"><?php echo htmlspecialchars($row['username']); ?></td>
                    <td data-label="Email"><?php echo htmlspecialchars($row['email'] ?: '—'); ?></td>
                    <td data-label="Role"><span class="badge <?php echo ($row['role']=='super_admin') ? 'badge-danger' : (($row['role']=='admin') ? 'badge-warning' : 'badge-info'); ?>"><?php echo $row['role']; ?></span></td>
                    <td data-label="Status"><span class="badge badge-success"><?php echo $row['status'] ?? 'active'; ?></span></td>
                    <td data-label="Actions">
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
        </div>
    </div>
    
    <div id="staff-tab" class="tab-content">
        <div style="background:#fef3c7; padding:15px; border-radius:12px; margin-bottom:20px;">
            💡 <strong>Staff Login Info:</strong> Staff members login with their <strong>Staff ID</strong> as username. 
            Default password is also their <strong>Staff ID</strong>.
        </div>
        
        <a href="employees.php"><button style="margin-bottom:20px;">➕ Add New Staff Member</button></a>

        <div class="search-section" style="margin: 0 0 14px;">
            <span class="search-icon">🔍</span>
            <input type="text" id="staffUserSearch" class="search-input" placeholder="Search staff (name/phone/staff ID/branch)..." onkeyup="filterTable('staffUserSearch','staffUsersTable')">
        </div>
        
        <h3 style="margin-bottom:12px;font-size:18px;">📋 Registered Staff Members</h3>
        <div class="table-card">
        <div style="overflow:auto;max-width:100%;">
        <table class="responsive-table" id="staffUsersTable">
            <thead>
                <tr><th>ID</th><th>Staff ID</th><th>Full Name</th><th>Email</th><th>Branch</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $staff_users->fetch_assoc()): ?>
                <tr>
                    <td data-label="ID"><?php echo $row['id']; ?></td>
                    <td data-label="Staff ID"><strong><?php echo htmlspecialchars($row['staff_id']); ?></strong></td>
                    <td data-label="Full Name"><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td data-label="Email"><?php echo htmlspecialchars($row['email'] ?: '—'); ?></td>
                    <td data-label="Branch"><?php echo htmlspecialchars($row['branch'] ?: '—'); ?></td>
                    <td data-label="Actions">
                        <a href="edit_employee.php?id=<?php echo $row['id']; ?>"><button class="action-btn edit-btn">Edit</button></a>
                        <a href="user.php?delete_staff_id=<?php echo urlencode($row['staff_id']); ?>" onclick="return confirm('Delete this staff member?');">
                           <button class="action-btn delete-btn">Delete</button>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($staff_users->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;">No staff members found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
        </div>
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

function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const filter = (input.value || '').toUpperCase();
    const table = document.getElementById(tableId);
    const tr = table.getElementsByTagName("tr");
    for (let i = 1; i < tr.length; i++) {
        let found = false;
        const tds = tr[i].getElementsByTagName("td");
        for (let j = 0; j < tds.length; j++) {
            if ((tds[j].textContent || '').toUpperCase().indexOf(filter) > -1) { found = true; break; }
        }
        tr[i].style.display = found ? "" : "none";
    }
}
</script>
<script src="/asset/js/ui-enhancements.js" defer></script>
</body>
</html>
