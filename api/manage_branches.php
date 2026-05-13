<?php
session_start();
include(__DIR__ . "/../includes/config.php");

// Only Admin/Super Admin
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    die("<h2 style='color:var(--danger);text-align:center;margin-top:100px;'>Access Denied.</h2>");
}

$message = "";
$msg_type = "";

// ---------------------------------------------------------------
// HANDLE FORM SUBMISSION
// ---------------------------------------------------------------
if (isset($_POST['add_branch'])) {
    $branch_name   = trim($_POST['branch_name']);
    $latitude      = (float)$_POST['latitude'];
    $longitude     = (float)$_POST['longitude'];
    $radius_meters = (int)$_POST['radius_meters'];

    $stmt = $conn->prepare("INSERT INTO branches (branch_name, latitude, longitude, radius_meters) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdi", $branch_name, $latitude, $longitude, $radius_meters);

    if ($stmt->execute()) {
        $message  = "Branch added successfully!";
        $msg_type = "success";
    } else {
        $message  = "Error: " . $conn->error;
        $msg_type = "error";
    }
    $stmt->close();
}

// ---------------------------------------------------------------
// DELETE BRANCH
// ---------------------------------------------------------------
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM branches WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $message = "Branch deleted successfully.";
    $msg_type = "success";
}

// FETCH BRANCHES
$branches = $conn->query("SELECT * FROM branches ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Branches & Geofencing</title>
    <link rel="stylesheet" href="asset/css/style.css">
    <style>
        .geo-box {
            background: var(--surface);
            padding: 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }
        .branch-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .branch-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow-sm);
        }
        .coordinates {
            font-family: monospace;
            font-size: 13px;
            color: var(--text-muted);
            background: var(--surface-alt);
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin: 8px 0;
        }
        .helper-text {
            font-size: 13px;
            color: var(--text-muted);
            margin: 8px 0;
            line-height: 1.6;
        }
    </style>
</head>
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<div class="content">
    <h2>📍 Manage Branches & Geofencing</h2>
    <p class="subtitle">Define your office locations and allowed clock-in radius.</p>

    <?php if ($message): ?>
        <p class="msg-<?php echo $msg_type; ?>"><?php echo $message; ?></p>
    <?php endif; ?>

    <!-- ADD BRANCH FORM -->
    <div class="geo-box">
        <h3>➕ Add Office Branch</h3>
        <p class="helper-text">To get coordinates, open Google Maps, right-click on your office, and copy the Latitude/Longitude.</p>
        
        <form method="POST">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div>
                    <label>Branch Name</label>
                    <input type="text" name="branch_name" placeholder="e.g. Lagos Head Office" required>
                </div>
                <div>
                    <label>Clock-in Radius (Meters)</label>
                    <input type="number" name="radius_meters" value="200" required>
                </div>
                <div>
                    <label>Latitude</label>
                    <input type="text" name="latitude" placeholder="e.g. 6.5244" required>
                </div>
                <div>
                    <label>Longitude</label>
                    <input type="text" name="longitude" placeholder="e.g. 3.3792" required>
                </div>
            </div>
            <button type="submit" name="add_branch" style="margin-top:16px;">Save Branch & Location</button>
        </form>
    </div>

    <!-- BRANCH LIST -->
    <h3>📌 Registered Branches</h3>
    <div class="branch-grid">
        <?php while ($b = $branches->fetch_assoc()): ?>
            <div class="branch-card">
                <h4><?php echo htmlspecialchars($b['branch_name']); ?></h4>
                <div class="coordinates">
                    LAT: <?php echo $b['latitude']; ?> | LNG: <?php echo $b['longitude']; ?>
                </div>
                <div style="font-size:14px; margin-bottom:12px;">
                    Allowed Radius: <strong><?php echo $b['radius_meters']; ?> meters</strong>
                </div>
                <a href="?delete_id=<?php echo $b['id']; ?>" 
                   onclick="return confirm('Delete this branch? Attendance logs will not be affected.');">
                   <button class="action-btn delete-btn" style="width:100%;">Delete Branch</button>
                </a>
            </div>
        <?php endwhile; ?>
    </div>

    <div class="footer">
        &copy; <?php echo date("Y"); ?> Attendance System | Geofencing Enabled
    </div>
</div>

</body>
</html>
