<?php
include(__DIR__ . "/../includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// ---------------------------------------------------------------
// AUTO-CREATE TABLE IF NOT EXISTS
// ---------------------------------------------------------------
$conn->query("CREATE TABLE IF NOT EXISTS `zk_devices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_name` VARCHAR(100) NOT NULL DEFAULT 'ZKTeco Device',
  `ip_address` VARCHAR(45) NOT NULL,
  `port` INT NOT NULL DEFAULT 4370,
  `location` VARCHAR(150) DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `api_key` VARCHAR(64) DEFAULT NULL,
  `last_sync` DATETIME DEFAULT NULL,
  `last_sync_status` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$message = "";
$msg_type = "";

// ---------------------------------------------------------------
// ADD DEVICE
// ---------------------------------------------------------------
if (isset($_POST['add_device'])) {
    $name     = trim($_POST['device_name']);
    $ip       = trim($_POST['ip_address']);
    $port     = (int)$_POST['port'];
    $location = trim($_POST['location']);
    $api_key  = bin2hex(random_bytes(16)); // Generate random API key

    $stmt = $conn->prepare("INSERT INTO zk_devices (device_name, ip_address, port, location, api_key) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $name, $ip, $port, $location, $api_key);

    if ($stmt->execute()) {
        $message  = "Device added successfully! API Key: <code>$api_key</code>";
        $msg_type = "success";
    } else {
        $message  = "Failed to add device.";
        $msg_type = "error";
    }
    $stmt->close();
}

// ---------------------------------------------------------------
// DELETE DEVICE
// ---------------------------------------------------------------
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM zk_devices WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $message  = "Device deleted.";
    $msg_type = "success";
}

// ---------------------------------------------------------------
// TOGGLE DEVICE STATUS
// ---------------------------------------------------------------
if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    $conn->query("UPDATE zk_devices SET status = IF(status='active','inactive','active') WHERE id = $id");
    $message  = "Device status updated.";
    $msg_type = "success";
}

// ---------------------------------------------------------------
// TEST CONNECTION
// ---------------------------------------------------------------
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

            if ($zk->connect()) {
                $version = $zk->getVersion() ?: 'Unknown';
                $serial  = $zk->getSerialNumber() ?: 'Unknown';
                $devTime = $zk->getTime() ?: 'Unknown';

                $message  = "✅ Connected to <strong>{$device['device_name']}</strong>!<br>";
                $message .= "Version: <code>$version</code> | Serial: <code>$serial</code> | Device Time: <code>$devTime</code>";
                $msg_type = "success";
                $zk->disconnect();
            } else {
                $message  = "❌ Failed to connect: Device unreachable or network error.";
                $msg_type = "error";
            }
        } else {
            $message  = "❌ ZKTeco library not found.";
            $msg_type = "error";
        }
    }
}

// ---------------------------------------------------------------
// FETCH DEVICES
// ---------------------------------------------------------------
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
  .device-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-top: 24px;
  }
  .device-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    position: relative;
    transition: transform .2s var(--ease), box-shadow .2s var(--ease);
  }
  .device-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
  }
  .device-card h3 {
    font-size: 18px;
    margin-bottom: 12px;
    color: var(--text);
  }
  .device-info { font-size: 13px; color: var(--text-muted); margin-bottom: 6px; }
  .device-info strong { color: var(--text); }
  .device-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
    flex-wrap: wrap;
  }
  .device-actions a, .device-actions button {
    font-size: 12px;
    padding: 7px 14px;
    border-radius: var(--radius-sm);
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all .2s var(--ease);
  }
  .btn-test { background: var(--info-bg); color: #1e40af; }
  .btn-sync { background: var(--success-bg); color: #065f46; }
  .btn-toggle { background: var(--warning-bg); color: #92400e; }
  .btn-delete { background: var(--danger-bg); color: #991b1b; }
  .status-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 6px;
    vertical-align: middle;
  }
  .status-dot.active { background: var(--success); }
  .status-dot.inactive { background: var(--danger); }

  .msg-box {
    padding: 14px 20px;
    border-radius: var(--radius);
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.7;
  }
  .msg-box.success { background: var(--success-bg); color: #065f46; border: 1px solid #6ee7b7; }
  .msg-box.error { background: var(--danger-bg); color: #991b1b; border: 1px solid #fca5a5; }
  
  #syncOverlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.55);
    backdrop-filter: blur(4px);
    z-index: 9999;
    justify-content: center;
    align-items: center;
  }
  #syncOverlay.show { display: flex; }
  .sync-spinner {
    background: var(--surface);
    padding: 40px 50px;
    border-radius: var(--radius-lg);
    text-align: center;
  }
</style>
</head>
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<div class="content">
  <h2>📡 Device Settings</h2>
  <p class="subtitle">Manage your ZKTeco biometric devices and sync attendance data.</p>

  <?php if ($message): ?>
    <div class="msg-box <?php echo $msg_type; ?>"><?php echo $message; ?></div>
  <?php endif; ?>

  <!-- ADD DEVICE FORM -->
  <form method="POST" style="margin-bottom:30px;">
    <h3 style="margin-bottom:16px;font-size:17px;">➕ Add New Device</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div>
        <label>Device Name</label>
        <input type="text" name="device_name" placeholder="e.g. Main Entrance" required>
      </div>
      <div>
        <label>IP Address</label>
        <input type="text" name="ip_address" placeholder="e.g. 192.168.1.201" required>
      </div>
      <div>
        <label>Port</label>
        <input type="text" name="port" value="4370" required>
      </div>
      <div>
        <label>Location</label>
        <input type="text" name="location" placeholder="e.g. Front Door, HR Office">
      </div>
    </div>
    <button type="submit" name="add_device" style="margin-top:12px;">Add Device</button>
  </form>

  <!-- DEVICES LIST -->
  <h3 style="margin-bottom:8px;font-size:17px;">🖥️ Registered Devices</h3>

  <?php if ($devices && $devices->num_rows > 0): ?>
    <div class="device-grid">
      <?php while ($d = $devices->fetch_assoc()): ?>
        <div class="device-card">
          <h3>
            <span class="status-dot <?php echo $d['status']; ?>"></span>
            <?php echo htmlspecialchars($d['device_name']); ?>
          </h3>
          <div class="device-info"><strong>IP:</strong> <?php echo $d['ip_address']; ?>:<?php echo $d['port']; ?></div>
          <div class="device-info"><strong>Location:</strong> <?php echo htmlspecialchars($d['location'] ?: '—'); ?></div>
          <div class="device-info"><strong>Status:</strong>
            <span class="badge <?php echo $d['status'] == 'active' ? 'badge-success' : 'badge-danger'; ?>">
              <?php echo $d['status']; ?>
            </span>
          </div>
          <div class="device-info"><strong>Last Sync:</strong> <?php echo $d['last_sync'] ?: 'Never'; ?></div>
          <div class="device-info"><strong>Last Result:</strong> <?php echo htmlspecialchars($d['last_sync_status'] ?: '—'); ?></div>

          <div class="device-actions">
            <a href="?test_id=<?php echo $d['id']; ?>" class="btn-test">🔌 Test</a>
            <a href="?toggle_id=<?php echo $d['id']; ?>" class="btn-toggle">
              <?php echo $d['status'] == 'active' ? '⏸ Disable' : '▶ Enable'; ?>
            </a>
            <a href="?delete_id=<?php echo $d['id']; ?>" class="btn-delete" onclick="return confirm('Delete this device?');">🗑 Delete</a>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div style="text-align:center; padding:40px; color:var(--text-muted);">
      <p style="font-size:48px;margin-bottom:12px;">📡</p>
      <p>No devices configured yet.</p>
    </div>
  <?php endif; ?>

  <div class="footer">
    &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Mbewu
  </div>
</div>

</body>
</html>
