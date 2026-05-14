<?php
include(__DIR__ . "/../includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$message = "";
$msg_type = "";

// ---------------------------------------------------------------
// HANDLE DEVICE ACTIONS (Add, Delete, Toggle, Test)
// ---------------------------------------------------------------
if (isset($_POST['add_device'])) {
    $name     = trim($_POST['device_name']);
    $ip       = trim($_POST['ip_address']);
    $port     = (int)$_POST['port'];
    $location = trim($_POST['location']);
    $api_key  = bin2hex(random_bytes(16));

    $stmt = $conn->prepare("INSERT INTO zk_devices (device_name, ip_address, port, location, api_key) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $name, $ip, $port, $location, $api_key);
    if ($stmt->execute()) { $message = "Device added! API Key: $api_key"; $msg_type = "success"; }
    else { $message = "Error adding device."; $msg_type = "error"; }
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

if (isset($_GET['test_id'])) {
    $id = (int)$_GET['test_id'];
    $stmt = $conn->prepare("SELECT * FROM zk_devices WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $device = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($device) {
        $lib_path = __DIR__ . "/lib/ZKTeco.php";
        if (file_exists($lib_path)) {
            include($lib_path);
            $zk = new ZKTeco($device['ip_address'], (int)$device['port'], 3);
            if ($zk->connect()) { $message = "✅ Connected to {$device['device_name']}!"; $msg_type = "success"; $zk->disconnect(); }
            else { $message = "❌ Connection failed. Check IP/Port."; $msg_type = "error"; }
        } else { $message = "❌ Library missing."; $msg_type = "error"; }
    }
}

$devices = $conn->query("SELECT * FROM zk_devices ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Device Settings</title>
<link rel="stylesheet" href="/asset/css/style.css">
<style>
    .device-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin: 24px 0; }
    .device-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); }
    .instruction-box { background: #f0f9ff; border: 1px solid #bae6fd; padding: 24px; border-radius: 20px; margin-top: 40px; }
    .instruction-box h3 { color: #0369a1; margin-bottom: 12px; }
    .instruction-box code { background: #e0f2fe; padding: 4px 8px; border-radius: 4px; font-weight: 600; color: #0369a1; }
</style>
</head>
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<div class="content">
  <h2>📡 Device Settings</h2>
  <p class="subtitle">Integrate physical ZKTeco biometric devices with your cloud system.</p>

  <?php if ($message): ?>
    <div class="badge <?php echo $msg_type === 'success' ? 'badge-success' : 'badge-danger'; ?>" style="display:block; padding:15px; margin-bottom:20px;">
        <?php echo $message; ?>
    </div>
  <?php endif; ?>

  <form method="POST" style="background:white; padding:24px; border-radius:20px; border:1px solid var(--border);">
    <h3 style="margin-bottom:16px;">Add New Device</h3>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
      <input type="text" name="device_name" placeholder="Device Name (e.g. Main Lobby)" required>
      <input type="text" name="ip_address" placeholder="IP Address (e.g. 192.168.1.201)" required>
      <input type="number" name="port" value="4370" required>
      <input type="text" name="location" placeholder="Location Notes">
    </div>
    <button type="submit" name="add_device" style="margin-top:16px;">Register Device</button>
  </form>

  <div class="device-grid">
    <?php while ($d = $devices->fetch_assoc()): ?>
      <div class="device-card">
        <h4><?php echo htmlspecialchars($d['device_name']); ?></h4>
        <p style="font-size:14px; color:var(--text-muted); margin:8px 0;">IP: <?php echo $d['ip_address']; ?>:<?php echo $d['port']; ?></p>
        <span class="badge <?php echo $d['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>"><?php echo ucfirst($d['status']); ?></span>
        <div style="margin-top:16px; display:flex; gap:10px;">
            <a href="?test_id=<?php echo $d['id']; ?>"><button class="action-btn edit-btn">Test</button></a>
            <a href="?toggle_id=<?php echo $d['id']; ?>"><button class="action-btn edit-btn">Toggle</button></a>
            <a href="?delete_id=<?php echo $d['id']; ?>" onclick="return confirm('Delete?')"><button class="action-btn delete-btn">Delete</button></a>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

  <div class="instruction-box">
    <h3>📖 Setup Instructions</h3>
    <p style="font-size:14px; color:#0369a1; line-height:1.6;">
        1. <strong>Networking</strong>: Ensure your ZKTeco device is on the same local network as the server sync script.<br>
        2. <strong>User IDs</strong>: The "User ID" on your biometric device MUST match the <code>Staff ID</code> in this system.<br>
        3. <strong>Auto-Sync</strong>: To sync data automatically to Vercel, run the <code>sync_attendance.php</code> script via Task Scheduler on a local PC:<br>
        <code>php.exe C:\path\to\sync_attendance.php</code><br>
        4. <strong>API Sync</strong>: Use the generated API Key to push data from your local network to this cloud dashboard safely.
    </p>
  </div>

  <div class="footer">&copy; <?php echo date("Y"); ?> Attendance System | Solomon Mbewu</div>
</div>
</body>
</html>
