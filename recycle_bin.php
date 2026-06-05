<?php
include(__DIR__ . "/includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    die("
    <div style='display:flex; height:100vh; align-items:center; justify-content:center; background:#0f172a; color:white; font-family:sans-serif;'>
        <div style='text-align:center;'>
            <h1 style='color:#ef4444; font-size:48px; margin-bottom:10px;'>Access Denied</h1>
            <p style='color:#94a3b8; font-size:18px;'>Only Super Admins can access the Recycle Bin.</p>
            <a href='dashboard.php' style='display:inline-block; margin-top:20px; padding:10px 20px; background:#3b82f6; color:white; text-decoration:none; border-radius:8px;'>Return to Dashboard</a>
        </div>
    </div>
    ");
}

$message = "";

// Handle Restore
if (isset($_GET['restore_type']) && isset($_GET['id'])) {
    $type = $_GET['restore_type'];
    $id = (int)$_GET['id'];
    
    if ($type === 'admin') {
        $conn->query("UPDATE `admin` SET deleted_at = NULL WHERE id = $id");
        $message = "<div class='alert success'>✅ Admin restored successfully.</div>";
    } elseif ($type === 'staff') {
        $conn->query("UPDATE `staff` SET deleted_at = NULL WHERE id = $id");
        $message = "<div class='alert success'>✅ Staff member restored successfully.</div>";
    }
}

// Handle Permanent Delete
if (isset($_GET['perm_delete_type']) && isset($_GET['id'])) {
    $type = $_GET['perm_delete_type'];
    $id = (int)$_GET['id'];
    
    if ($type === 'admin') {
        $conn->query("DELETE FROM `admin` WHERE id = $id");
        $message = "<div class='alert error'>🗑️ Admin permanently deleted.</div>";
    } elseif ($type === 'staff') {
        $conn->query("DELETE FROM `staff` WHERE id = $id");
        $message = "<div class='alert error'>🗑️ Staff member permanently deleted.</div>";
    }
}

$deleted_admins = $conn->query("SELECT id, username, email, deleted_at FROM `admin` WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
$deleted_staff = $conn->query("SELECT id, staff_id, full_name, branch, deleted_at FROM `staff` WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");

$total_items = ($deleted_admins ? $deleted_admins->num_rows : 0) + ($deleted_staff ? $deleted_staff->num_rows : 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recycle Bin</title>
<link rel="stylesheet" href="/asset/css/style.css">
<style>
    body {
        background: #f8fafc;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .rb-header {
        background: linear-gradient(135deg, #1e293b, #0f172a);
        color: white;
        padding: 40px;
        border-radius: 24px;
        margin-bottom: 30px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .rb-header h2 { margin: 0; font-size: 28px; display: flex; align-items: center; gap: 10px; }
    .rb-header .badge-count {
        background: #ef4444; color: white; padding: 4px 12px; border-radius: 20px; font-size: 14px; font-weight: bold;
    }
    .rb-header p { margin: 8px 0 0; color: #94a3b8; font-size: 14px; }
    
    .glass-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
        border-radius: 20px;
        padding: 24px;
        margin-bottom: 30px;
        transition: transform 0.3s ease;
    }
    .glass-card:hover {
        transform: translateY(-2px);
    }
    .glass-card h3 {
        margin-top: 0; margin-bottom: 16px; color: #1e293b; font-size: 18px;
        display: flex; align-items: center; gap: 8px;
    }
    
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 16px; font-size: 14px; color: #64748b; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
    td { padding: 16px; font-size: 15px; color: #334155; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr { transition: background 0.2s; }
    tr:hover { background: rgba(255, 255, 255, 0.9); }
    
    .btn { padding: 8px 16px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 13px; text-decoration: none; display: inline-block; }
    .btn-restore { background: #10b981; color: white; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); }
    .btn-restore:hover { background: #059669; transform: translateY(-1px); box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3); }
    .btn-delete { background: #fee2e2; color: #ef4444; }
    .btn-delete:hover { background: #fecaca; color: #dc2626; }
    
    .empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; }
    .empty-state svg { width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.5; }
    
    .alert { padding: 16px 20px; border-radius: 16px; margin-bottom: 24px; font-weight: 600; animation: slideDown 0.3s ease-out; }
    .alert.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
</head>
<body>

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
    
    <div class="rb-header">
        <div>
            <h2>🗑️ Recycle Bin <span class="badge-count"><?php echo $total_items; ?> Items</span></h2>
            <p>Deleted items are kept here for 30 days before being permanently removed.</p>
        </div>
    </div>
    
    <?php if ($message) echo $message; ?>

    <div class="glass-card">
        <h3>👥 Deleted Staff</h3>
        <?php if ($deleted_staff && $deleted_staff->num_rows > 0): ?>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Full Name</th>
                        <th>Branch</th>
                        <th>Deleted On</th>
                        <th>Days Left</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = $deleted_staff->fetch_assoc()): 
                        $del_time = strtotime($r['deleted_at']);
                        $days_left = 30 - floor((time() - $del_time) / 86400);
                        $days_left = max(0, $days_left);
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['staff_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['branch'] ?: '—'); ?></td>
                        <td><?php echo date('M d, Y h:i A', $del_time); ?></td>
                        <td><span style="color: <?php echo $days_left < 5 ? '#ef4444' : '#64748b'; ?>; font-weight:600;"><?php echo $days_left; ?> days</span></td>
                        <td style="display:flex; gap:8px;">
                            <a href="?restore_type=staff&id=<?php echo $r['id']; ?>" class="btn btn-restore" onclick="return confirm('Restore this staff member?');">↺ Restore</a>
                            <a href="?perm_delete_type=staff&id=<?php echo $r['id']; ?>" class="btn btn-delete" onclick="return confirm('Permanently delete? This cannot be undone.');">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            <p>No deleted staff members.</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="glass-card">
        <h3>👑 Deleted Admins</h3>
        <?php if ($deleted_admins && $deleted_admins->num_rows > 0): ?>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Deleted On</th>
                        <th>Days Left</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = $deleted_admins->fetch_assoc()): 
                        $del_time = strtotime($r['deleted_at']);
                        $days_left = 30 - floor((time() - $del_time) / 86400);
                        $days_left = max(0, $days_left);
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['email'] ?: '—'); ?></td>
                        <td><?php echo date('M d, Y h:i A', $del_time); ?></td>
                        <td><span style="color: <?php echo $days_left < 5 ? '#ef4444' : '#64748b'; ?>; font-weight:600;"><?php echo $days_left; ?> days</span></td>
                        <td style="display:flex; gap:8px;">
                            <a href="?restore_type=admin&id=<?php echo $r['id']; ?>" class="btn btn-restore" onclick="return confirm('Restore this admin?');">↺ Restore</a>
                            <a href="?perm_delete_type=admin&id=<?php echo $r['id']; ?>" class="btn btn-delete" onclick="return confirm('Permanently delete? This cannot be undone.');">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            <p>No deleted admin users.</p>
        </div>
        <?php endif; ?>
    </div>
    
</div>

</body>
</html>
