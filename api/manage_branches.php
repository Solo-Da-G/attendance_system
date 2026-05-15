<?php

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
    $stmt->bind_param("sddi", $branch_name, $latitude, $longitude, $radius_meters);

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
    <link rel="stylesheet" href="/asset/css/style.css">
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
                    <input type="number" name="radius_meters" value="300" required>
                </div>
                <div>
                    <label>Latitude</label>
                    <input type="text" id="newBranchLat" name="latitude" placeholder="e.g. 6.5829207" required>
                </div>
                <div>
                    <label>Longitude</label>
                    <input type="text" id="newBranchLng" name="longitude" placeholder="e.g. 3.3797377" required>
                </div>
            </div>
            <button type="button" onclick="fillBranchGps('newBranchLat','newBranchLng')" style="margin-top:12px;background:#0f766e;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;">📍 Use my device GPS</button>
            <button type="submit" name="add_branch" style="margin-top:12px;margin-left:8px;">Save Branch & Location</button>
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
                <button type="button" class="action-btn edit-btn" style="width:100%;margin-bottom:8px;"
                    onclick="updateBranchGps(<?php echo (int)$b['id']; ?>, '<?php echo htmlspecialchars($b['branch_name'], ENT_QUOTES); ?>')">
                    📍 Set coords from my device GPS
                </button>
                <a href="?delete_id=<?php echo $b['id']; ?>" 
                   onclick="return confirm('Delete this branch? Attendance logs will not be affected.');">
                   <button class="action-btn delete-btn" style="width:100%;">Delete Branch</button>
                </a>
            </div>
        <?php endwhile; ?>
    </div>

    <div style="background:#fdf4ff; border:1px solid #f5d0fe; padding:24px; border-radius:20px; margin-top:40px;">
        <h3 style="color:#86198f; margin-bottom:12px;">🌍 How Geofencing Works</h3>
        <p style="font-size:14px; color:#86198f; line-height:1.6;">
            1. <strong>Define Branch</strong>: Add your office location above by providing the exact Latitude and Longitude.<br>
            2. <strong>Set Radius</strong>: The "Radius" is the allowed distance (in meters) from the center of the office. 200m is recommended.<br>
            3. <strong>Staff Clock-in</strong>: When staff log in to the dashboard, the system will ask for their location. If they are within the radius of their assigned branch, they can clock in.<br>
            4. <strong>Security</strong>: This ensures staff can only clock in when they are actually at the office premises.
        </p>
    </div>

    <div class="footer">
        &copy; <?php echo date("Y"); ?> Attendance System | Geofencing Enabled
    </div>
</div>

<script>
function fillBranchGps(latId, lngId) {
    if (!navigator.geolocation) { alert('GPS not available'); return; }
    navigator.geolocation.getCurrentPosition(function(pos) {
        document.getElementById(latId).value = pos.coords.latitude.toFixed(7);
        document.getElementById(lngId).value = pos.coords.longitude.toFixed(7);
        alert('GPS filled: ' + pos.coords.latitude.toFixed(5) + ', ' + pos.coords.longitude.toFixed(5));
    }, function() { alert('Could not get GPS. Allow location on this device.'); },
    { enableHighAccuracy: true, timeout: 25000 });
}

function updateBranchGps(branchId, branchName) {
    if (!confirm('Update "' + branchName + '" to this device\'s current GPS?')) return;
    if (!navigator.geolocation) { alert('GPS not available'); return; }
    navigator.geolocation.getCurrentPosition(async function(pos) {
        const fd = new FormData();
        fd.append('branch_id', branchId);
        fd.append('lat', pos.coords.latitude);
        fd.append('lng', pos.coords.longitude);
        const res = await fetch('update_branch_gps.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        alert(data.message || (data.status === 'success' ? 'Updated!' : 'Failed'));
        if (data.status === 'success') location.reload();
    }, function() { alert('GPS failed'); }, { enableHighAccuracy: true, timeout: 25000 });
}
</script>

</body>
</html>


