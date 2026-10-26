<?php

include(__DIR__ . "/includes/config.php");

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0f172a">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="TDS Attendance">
  <link rel="manifest" href="/manifest.json">
  <link rel="apple-touch-icon" href="/asset/img/tds_logo.png">
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
<body class="app-page branches-page">

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

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
    <div class="search-section" style="margin: 8px 0 16px;">
        <span class="search-icon">🔍</span>
        <input type="text" id="branchSearch" class="search-input" placeholder="Search branches..." onkeyup="filterBranches()">
    </div>

    <h3>📌 Registered Branches</h3>
    <div class="branch-grid">
        <?php while ($b = $branches->fetch_assoc()): ?>
            <div class="branch-card" data-branch="<?php echo htmlspecialchars(strtoupper($b['branch_name'])); ?>">
                <h4><?php echo htmlspecialchars($b['branch_name']); ?></h4>
                <div class="coordinates">
                    LAT: <?php echo $b['latitude']; ?> | LNG: <?php echo $b['longitude']; ?>
                </div>
                    <div class="radius-controller" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px;margin-bottom:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <span style="font-size:13px;font-weight:700;color:#334155;">📍 Allowed Radius:</span>
                            <span id="radius-badge-<?php echo $b['id']; ?>" style="font-size:12px;background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:6px;font-weight:700;"><strong id="radius-val-<?php echo $b['id']; ?>"><?php echo (int)$b['radius_meters']; ?></strong> meters</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                            <button type="button" onclick="adjustRadius(<?php echo (int)$b['id']; ?>, -50)" style="padding:6px 12px;background:#e2e8f0;color:#1e293b;border:none;border-radius:8px;font-weight:800;cursor:pointer;font-size:13px;" title="Decrease 50m">- 50m</button>
                            <input type="number" id="radius-input-<?php echo $b['id']; ?>" value="<?php echo (int)$b['radius_meters']; ?>" min="10" max="10000" step="10" style="flex:1;min-width:70px;padding:8px 10px;border:1.5px solid #cbd5e1;border-radius:8px;text-align:center;font-weight:700;font-size:14px;" aria-label="Radius in meters">
                            <button type="button" onclick="adjustRadius(<?php echo (int)$b['id']; ?>, 50)" style="padding:6px 12px;background:#e2e8f0;color:#1e293b;border:none;border-radius:8px;font-weight:800;cursor:pointer;font-size:13px;" title="Increase 50m">+ 50m</button>
                            <button type="button" id="save-btn-<?php echo $b['id']; ?>" class="action-btn edit-btn" onclick="saveBranchRadius(<?php echo (int)$b['id']; ?>, '<?php echo htmlspecialchars($b['branch_name'], ENT_QUOTES); ?>')" style="padding:8px 14px;background:#10439f;color:#fff;border-radius:8px;font-weight:700;cursor:pointer;">💾 Save</button>
                        </div>
                        <div id="status-msg-<?php echo $b['id']; ?>" style="display:none;font-size:12px;font-weight:600;padding:6px 8px;border-radius:6px;margin-top:6px;"></div>
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
function filterBranches() {
    const q = (document.getElementById('branchSearch').value || '').toUpperCase();
    document.querySelectorAll('.branch-card').forEach(card => {
        const name = card.getAttribute('data-branch') || '';
        card.style.display = name.indexOf(q) > -1 ? '' : 'none';
    });
}

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
function adjustRadius(branchId, delta) {
    const input = document.getElementById('radius-input-' + branchId);
    if (!input) return;
    let val = parseInt(input.value, 10) || 100;
    val = Math.max(10, val + delta);
    input.value = val;
}

async function saveBranchRadius(branchId, branchName) {
    const input = document.getElementById('radius-input-' + branchId);
    const saveBtn = document.getElementById('save-btn-' + branchId);
    const badgeVal = document.getElementById('radius-val-' + branchId);
    const statusBox = document.getElementById('status-msg-' + branchId);
    if (!input) return;

    const radius = parseInt(input.value, 10);
    if (isNaN(radius) || radius <= 0) {
        alert('Please enter a valid positive radius (in meters).');
        return;
    }

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerText = 'Saving…';
    }

    try {
        const fd = new FormData();
        fd.append('branch_id', branchId);
        fd.append('radius_meters', radius);

        const res = await fetch('update_branch_radius.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerText = '💾 Save';
        }

        if (data.status === 'success') {
            if (badgeVal) badgeVal.innerText = radius;
            if (statusBox) {
                statusBox.style.display = 'block';
                statusBox.style.background = '#f0fdf4';
                statusBox.style.color = '#15803d';
                statusBox.style.border = '1px solid #bbf7d0';
                statusBox.innerHTML = '✅ ' + (data.message || 'Radius updated!');
                setTimeout(() => { statusBox.style.display = 'none'; }, 6000);
            }
        } else {
            if (statusBox) {
                statusBox.style.display = 'block';
                statusBox.style.background = '#fef2f2';
                statusBox.style.color = '#b91c1c';
                statusBox.style.border = '1px solid #fecaca';
                statusBox.innerHTML = '❌ ' + (data.message || 'Failed to update');
            }
        }
    } catch (err) {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerText = '💾 Save';
        }
        if (statusBox) {
            statusBox.style.display = 'block';
            statusBox.style.background = '#fef2f2';
            statusBox.style.color = '#b91c1c';
            statusBox.innerHTML = '❌ Network error updating radius.';
        }
    }
}
</script>


<script src="/asset/js/ui-enhancements.js" defer></script>
</body>
</html>


