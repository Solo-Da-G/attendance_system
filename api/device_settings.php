<?php
include(__DIR__ . "/../includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$message = "";
$msg_type = "";

// ---------------------------------------------------------------
// HANDLE DEVICE ACTIONS
// ---------------------------------------------------------------
if (isset($_POST['add_device'])) {
    $name     = trim($_POST['device_name']);
    $ip       = trim($_POST['ip_address']);
    $port     = (int)$_POST['port'];
    $location = trim($_POST['location']);
    $api_key  = bin2hex(random_bytes(16));

    $stmt = $conn->prepare("INSERT INTO zk_devices (device_name, ip_address, port, location, api_key) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $name, $ip, $port, $location, $api_key);
    if ($stmt->execute()) { $message = "✅ Device registered! Copy your API Key: <code>$api_key</code>"; $msg_type = "success"; }
    else { $message = "❌ Error adding device."; $msg_type = "error"; }
    $stmt->close();
}

if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM zk_devices WHERE id = $id");
    $message = "Device deleted."; $msg_type = "success";
}

if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    $conn->query("UPDATE zk_devices SET status = IF(status='active','inactive','active') WHERE id = $id");
    $message = "Status updated."; $msg_type = "success";
}

$devices = $conn->query("SELECT * FROM zk_devices ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZKTeco & Device Management</title>
<link rel="stylesheet" href="/asset/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
    .device-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; margin: 30px 0; }
    .device-card { background: white; border-radius: 24px; padding: 30px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: transform 0.3s var(--ease); }
    .device-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    
    .guide-section { background: white; border-radius: 32px; padding: 40px; border: 1px solid var(--border); margin-top: 50px; box-shadow: var(--shadow-sm); }
    .guide-section h3 { font-size: 24px; font-weight: 800; margin-bottom: 20px; color: #1e293b; display: flex; align-items: center; gap: 10px; }
    .step-list { list-style: none; padding: 0; }
    .step-item { display: flex; gap: 20px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #f1f5f9; }
    .step-item:last-child { border-bottom: none; }
    .step-number { background: var(--primary); color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0; }
    .step-content h4 { font-size: 16px; font-weight: 700; color: #334155; margin-bottom: 6px; }
    .step-content p { font-size: 14px; color: #64748b; line-height: 1.6; }
    
    .compatibility-tag { display: inline-block; padding: 6px 12px; background: #f1f5f9; border-radius: 8px; font-size: 12px; font-weight: 700; color: #475569; margin: 4px; }
    code { background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-family: monospace; }
</style>
</head>
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<div class="content">
  <div style="background: linear-gradient(135deg, #0f172a, #1e293b); color:white; padding:40px; border-radius:24px; margin-bottom:30px;">
    <h2 style="margin:0; font-size:32px;">📡 Device Management</h2>
    <p style="opacity:0.7; margin-top:10px;">Connect ZKTeco hardware and monitor real-time synchronization.</p>
  </div>

  <?php if ($message): ?>
    <div style="padding:20px; background:white; border-radius:16px; margin-bottom:30px; border-left:5px solid var(--primary); box-shadow:var(--shadow-sm);">
        <?php echo $message; ?>
    </div>
  <?php endif; ?>

  <div style="background:white; padding:35px; border-radius:24px; border:1px solid var(--border); box-shadow:var(--shadow-sm);">
    <h3 style="margin-bottom:20px; font-weight:800;">➕ Register New ZKTeco Device</h3>
    <form method="POST">
      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px;">
        <div>
            <label style="font-weight:700; font-size:13px; color:#64748b; display:block; margin-bottom:8px;">DEVICE NAME</label>
            <input type="text" name="device_name" placeholder="e.g. Lagos Front Office" style="width:100%;" required>
        </div>
        <div>
            <label style="font-weight:700; font-size:13px; color:#64748b; display:block; margin-bottom:8px;">IP ADDRESS</label>
            <input type="text" name="ip_address" placeholder="e.g. 192.168.1.201" style="width:100%;" required>
        </div>
        <div>
            <label style="font-weight:700; font-size:13px; color:#64748b; display:block; margin-bottom:8px;">PORT</label>
            <input type="number" name="port" value="4370" style="width:100%;" required>
        </div>
        <div>
            <label style="font-weight:700; font-size:13px; color:#64748b; display:block; margin-bottom:8px;">LOCATION</label>
            <input type="text" name="location" placeholder="e.g. Ground Floor" style="width:100%;">
        </div>
      </div>
      <button type="submit" name="add_device" style="margin-top:25px; padding:16px 40px; font-weight:800; border-radius:16px;">Register & Generate API Key</button>
    </form>
  </div>

  <div class="device-grid">
    <?php while ($d = $devices->fetch_assoc()): ?>
      <div class="device-card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px;">
            <h4 style="margin:0; font-size:18px; font-weight:800;"><?php echo htmlspecialchars($d['device_name']); ?></h4>
            <span class="badge <?php echo $d['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>"><?php echo strtoupper($d['status']); ?></span>
        </div>
        <p style="font-size:14px; color:#64748b; margin-bottom:20px;">
            <strong>IP:</strong> <?php echo $d['ip_address']; ?>:<?php echo $d['port']; ?><br>
            <strong>API Key:</strong> <code><?php echo $d['api_key']; ?></code>
        </p>
        <div style="display:flex; gap:10px;">
            <a href="?toggle_id=<?php echo $d['id']; ?>" style="flex:1;"><button class="action-btn edit-btn" style="width:100%; padding:10px;">Toggle</button></a>
            <a href="?delete_id=<?php echo $d['id']; ?>" style="flex:1;" onclick="return confirm('Delete this device?')"><button class="action-btn delete-btn" style="width:100%; padding:10px;">Delete</button></a>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

  <div class="guide-section">
    <h3>📖 ZKTeco Synchronization Guide</h3>
    <p style="margin-bottom:30px; color:#64748b;">Follow these steps to link your physical biometric device to this cloud dashboard.</p>
    
    <div class="step-list">
        <div class="step-item">
            <div class="step-number">1</div>
            <div class="step-content">
                <h4>Network Configuration</h4>
                <p>Connect your ZKTeco device to your local router via Ethernet. Assign it a static IP (e.g., <code>192.168.1.201</code>) and ensure port <code>4370</code> is open.</p>
            </div>
        </div>
        <div class="step-item">
            <div class="step-number">2</div>
            <div class="step-content">
                <h4>Admin Laptop Setup</h4>
                <p>Since this site is live on Vercel, it cannot "reach" your local IP directly. You must run the <code>sync_attendance.php</code> script on a laptop connected to the same network as the device.</p>
            </div>
        </div>
        <div class="step-item">
            <div class="step-number">3</div>
            <div class="step-content">
                <h4>Syncing to Cloud</h4>
                <p>The local script reads thumbprints from the device and "pushes" them to this site using your <strong>API Key</strong>. This ensures real-time updates on the Super Admin dashboard even when using geofencing on phones.</p>
            </div>
        </div>
        <div class="step-item">
            <div class="step-number">4</div>
            <div class="step-content">
                <h4>Phone & Geofencing</h4>
                <p>Staff can also clock in via their phones. When they do, their GPS location is verified against the office coordinates. Both thumbprint logs and phone logs are merged into the same dashboard instantly.</p>
            </div>
        </div>
    </div>

    <div style="margin-top:40px; padding-top:30px; border-top:1px solid #f1f5f9;">
        <h4 style="margin-bottom:15px; font-weight:800;">✅ Compatible Devices</h4>
        <span class="compatibility-tag">ZKTeco K40</span>
        <span class="compatibility-tag">ZKTeco MB20</span>
        <span class="compatibility-tag">ZKTeco iClock Series</span>
        <span class="compatibility-tag">ZKTeco UA Series</span>
        <span class="compatibility-tag">ZKTeco SilkID</span>
        <span class="compatibility-tag">ZKTeco SpeedFace</span>
    </div>
  </div>

  <div class="footer" style="text-align:center; margin-top:50px;">&copy; <?php echo date("Y"); ?> Attendance System | Solomon Mbewu</div>
</div>
</body>
</html>
