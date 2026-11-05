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
// EDIT BRANCH & COORDINATES
// ---------------------------------------------------------------
if (isset($_POST['edit_branch'])) {
    $branch_id     = (int)$_POST['branch_id'];
    $branch_name   = trim($_POST['branch_name']);
    $latitude      = (float)$_POST['latitude'];
    $longitude     = (float)$_POST['longitude'];
    $radius_meters = (int)$_POST['radius_meters'];
    $sync_staff    = !empty($_POST['sync_staff']);

    // Fetch previous branch name
    $old_name = '';
    $chk = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
    if ($chk) {
        $chk->bind_param("i", $branch_id);
        $chk->execute();
        $r = $chk->get_result()->fetch_assoc();
        $old_name = $r['branch_name'] ?? '';
        $chk->close();
    }

    $stmt = $conn->prepare("UPDATE branches SET branch_name = ?, latitude = ?, longitude = ?, radius_meters = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("sddii", $branch_name, $latitude, $longitude, $radius_meters, $branch_id);
        if ($stmt->execute()) {
            $message = "Branch '{$branch_name}' updated successfully! (Coords: {$latitude}, {$longitude})";
            $msg_type = "success";

            if ($sync_staff) {
                $stUp = $conn->prepare("UPDATE staff SET clock_lat = ?, clock_lng = ?, clock_radius = ?, branch = ? WHERE LOWER(TRIM(branch)) = LOWER(TRIM(?)) OR LOWER(TRIM(branch)) = LOWER(TRIM(?))");
                if ($stUp) {
                    $stUp->bind_param("ddisss", $latitude, $longitude, $radius_meters, $branch_name, $old_name, $branch_name);
                    $stUp->execute();
                    $aff = $conn->affected_rows;
                    $stUp->close();
                    if ($aff > 0) {
                        $message .= " Also synced coordinates for {$aff} staff member(s).";
                    }
                }
            }
        } else {
            $message = "Error updating branch: " . $conn->error;
            $msg_type = "error";
        }
        $stmt->close();
    }
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
        .branch-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 768px) {
            .geo-box {
                padding: 16px !important;
                margin-bottom: 20px !important;
            }
            .branch-grid {
                grid-template-columns: 1fr !important;
                gap: 14px !important;
            }
            .branch-form-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }
            .radius-controller div[style*="display:flex;align-items:center;gap:6px"] {
                flex-wrap: wrap !important;
            }
            .radius-controller button,
            .radius-controller input {
                min-height: 40px !important;
            }
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
        <p class="helper-text">
            To get coordinates, open Google Maps, right-click on your office, and copy the Latitude/Longitude.<br>
            <span style="color:#b45309;font-weight:600;">⚠️ Important: Only click "Use my device GPS" if you are physically inside the office. If you are creating or updating a Benin City branch from Lagos, do NOT use device GPS — type the real Benin coordinates manually or click the preset button below!</span>
        </p>
        
        <form method="POST">
            <div class="branch-form-grid">
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
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center;">
                <button type="button" onclick="fillBranchGps('newBranchLat','newBranchLng')" style="background:#0f766e;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:600;">📍 Use my device GPS</button>
                <button type="button" onclick="document.getElementById('newBranchLat').value='6.339722';document.getElementById('newBranchLng').value='5.625278';" style="background:#10b981;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:700;">🏛️ Fill Benin City (6.339722, 5.625278)</button>
                <button type="submit" name="add_branch" style="margin-left:auto;padding:10px 20px;font-weight:700;">Save Branch & Location</button>
            </div>
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
                    <a href="https://www.google.com/maps?q=<?php echo $b['latitude']; ?>,<?php echo $b['longitude']; ?>" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:none;font-weight:700;font-size:12px;margin-left:8px;" title="View on Google Maps">🗺️ Map</a>
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
                <button type="button" class="action-btn" style="width:100%;margin-bottom:8px;background:#2563eb;color:#fff;border-radius:8px;font-weight:700;padding:10px;cursor:pointer;"
                    onclick="openEditModal(<?php echo (int)$b['id']; ?>, '<?php echo htmlspecialchars($b['branch_name'], ENT_QUOTES); ?>', '<?php echo $b['latitude']; ?>', '<?php echo $b['longitude']; ?>', <?php echo (int)$b['radius_meters']; ?>)">
                    ✏️ Edit Location & Coordinates
                </button>
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

    <!-- EDIT BRANCH MODAL -->
    <div id="editBranchModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px;">
        <div style="background:var(--surface,#fff);border-radius:16px;max-width:540px;width:100%;padding:24px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.3);border:1px solid var(--border,#cbd5e1);position:relative;max-height:92vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3 style="margin:0;font-size:18px;font-weight:800;color:var(--text,#0f172a);">✏️ Edit Branch Coordinates</h3>
                <button type="button" onclick="closeEditModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#64748b;line-height:1;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="branch_id" id="edit_branch_id">
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:4px;">Branch Name</label>
                    <input type="text" name="branch_name" id="edit_branch_name" required style="width:100%;padding:10px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:14px;box-sizing:border-box;">
                </div>
                <div class="branch-form-grid" style="margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:4px;">Latitude</label>
                        <input type="text" name="latitude" id="edit_latitude" step="any" placeholder="e.g. 6.339722" required style="width:100%;padding:10px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:14px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:4px;">Longitude</label>
                        <input type="text" name="longitude" id="edit_longitude" step="any" placeholder="e.g. 5.625278" required style="width:100%;padding:10px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:14px;box-sizing:border-box;">
                    </div>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:4px;">Clock-in Allowed Radius (Meters)</label>
                    <input type="number" name="radius_meters" id="edit_radius_meters" min="10" max="10000" step="10" required style="width:100%;padding:10px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:14px;box-sizing:border-box;">
                </div>

                <!-- Quick presets -->
                <div style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-bottom:16px;">
                    <div style="font-weight:800;font-size:12px;color:#334155;margin-bottom:8px;">⚡ QUICK PRESETS:</div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" onclick="setEditCoords('6.339722', '5.625278')" style="background:#10b981;color:#fff;border:none;border-radius:6px;padding:7px 12px;font-size:12px;font-weight:700;cursor:pointer;">
                            🏛️ Set Benin City (6.339722, 5.625278)
                        </button>
                        <button type="button" onclick="fillBranchGps('edit_latitude', 'edit_longitude')" style="background:#0f766e;color:#fff;border:none;border-radius:6px;padding:7px 12px;font-size:12px;font-weight:700;cursor:pointer;">
                            📍 Use Device GPS (if present in office)
                        </button>
                    </div>
                    <p style="font-size:11px;color:#64748b;margin:6px 0 0;">(Benin City: 66b Mission Road, Benin City, Edo State)</p>
                </div>

                <div style="margin-bottom:20px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px;">
                    <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;cursor:pointer;margin:0;">
                        <input type="checkbox" name="sync_staff" value="1" checked style="width:18px;height:18px;accent-color:#2563eb;margin-top:2px;">
                        <div>
                            <strong style="color:#1e40af;display:block;">Sync all staff assigned to this branch</strong>
                            <span style="font-size:12px;color:#3b82f6;">Automatically updates the stored GPS coordinates for all staff in this branch so they can clock in/out immediately.</span>
                        </div>
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" onclick="closeEditModal()" style="padding:10px 18px;background:#e2e8f0;color:#334155;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Cancel</button>
                    <button type="submit" name="edit_branch" style="padding:10px 22px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;">💾 Save Changes</button>
                </div>
            </form>
        </div>
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

function openEditModal(id, name, lat, lng, radius) {
    document.getElementById('edit_branch_id').value = id;
    document.getElementById('edit_branch_name').value = name;
    document.getElementById('edit_latitude').value = lat;
    document.getElementById('edit_longitude').value = lng;
    document.getElementById('edit_radius_meters').value = radius;
    const modal = document.getElementById('editBranchModal');
    if (modal) modal.style.display = 'flex';
}

function closeEditModal() {
    const modal = document.getElementById('editBranchModal');
    if (modal) modal.style.display = 'none';
}

function setEditCoords(lat, lng) {
    document.getElementById('edit_latitude').value = lat;
    document.getElementById('edit_longitude').value = lng;
}

// Close modal when clicking outside or pressing Escape
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEditModal();
});
document.addEventListener('click', function(e) {
    const modal = document.getElementById('editBranchModal');
    if (modal && e.target === modal) closeEditModal();
});
</script>


<script src="/asset/js/ui-enhancements.js" defer></script>
</body>
</html>


