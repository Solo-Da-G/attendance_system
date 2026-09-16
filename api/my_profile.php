<?php
include(__DIR__ . "/includes/config.php");

if (empty($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

$staff_id = $_SESSION['staff_id'];
$stmt = $conn->prepare("SELECT staff_id, full_name, job_title, email, phone, department, branch, photo FROM staff WHERE staff_id = ? LIMIT 1");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff) {
    header("Location: logout.php");
    exit;
}

// Ensure staff_biometrics table exists
$conn->query("CREATE TABLE IF NOT EXISTS `staff_biometrics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` VARCHAR(50) NOT NULL,
    `credential_id` VARCHAR(255) NOT NULL,
    `public_key` TEXT NOT NULL,
    `device_name` VARCHAR(150) DEFAULT 'Mobile / PC Fingerprint',
    `counter` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(`staff_id`),
    UNIQUE KEY `uniq_cred` (`credential_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle delete biometric
if (isset($_POST['delete_bio_id'])) {
    $bio_id = (int)$_POST['delete_bio_id'];
    $del = $conn->prepare("DELETE FROM staff_biometrics WHERE id = ? AND staff_id = ?");
    if ($del) {
        $del->bind_param("is", $bio_id, $staff_id);
        $del->execute();
        $del->close();
    }
    header("Location: my_profile.php?msg=bio_deleted");
    exit;
}

// Fetch enrolled biometrics
$bio_stmt = $conn->prepare("SELECT id, credential_id, device_name, created_at FROM staff_biometrics WHERE staff_id = ? ORDER BY id DESC");
$bio_devices = [];
if ($bio_stmt) {
    $bio_stmt->bind_param("s", $staff_id);
    $bio_stmt->execute();
    $b_res = $bio_stmt->get_result();
    while ($br = $b_res->fetch_assoc()) {
        $bio_devices[] = $br;
    }
    $bio_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — Attendance System</title>
<link rel="stylesheet" href="/asset/css/style.css">
<style>
  body { background: var(--bg); }
  .profile-wrap { display: grid; grid-template-columns: 1fr; gap: 24px; margin-top: 18px; }
  .profile-card { background: var(--surface); border: 1px solid rgba(226, 232, 240, 0.6); border-radius: var(--radius-xl); box-shadow: var(--shadow-lg); padding: 28px; }
  .profile-head { display: flex; align-items: center; gap: 16px; }
  .profile-pic { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(16, 67, 159, .35); background: #111827; }
  .profile-name { font-size: 20px; font-weight: 800; margin: 0; color: var(--text); }
  .profile-sub { margin: 2px 0 0; color: var(--text-muted); font-weight: 600; }
  .kv { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 18px; }
  .kv-row { display: grid; grid-template-columns: 160px 1fr; gap: 12px; padding: 10px 14px; background: var(--surface-alt); border: 1px solid var(--border); border-radius: 14px; }
  .kv-row span { color: var(--text-muted); font-weight: 600; font-size: 13.5px; }
  .kv-row b { color: var(--text); font-weight: 700; font-size: 14px; }
  .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
  .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 18px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface); font-weight: 700; color: var(--text); box-shadow: var(--shadow-sm); text-decoration: none; font-size: 13.5px; transition: all 0.2s; }
  .btn:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
  .btn.primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: #fff; border-color: transparent; }
  
  .bio-list { list-style: none; padding: 0; margin: 16px 0 0; display: flex; flex-direction: column; gap: 10px; }
  .bio-item { display: flex; align-items: center; justify-content: space-between; background: var(--surface-alt); border: 1px solid var(--border); padding: 12px 16px; border-radius: 14px; }
  .bio-item-info { display: flex; align-items: center; gap: 12px; }
  .bio-item-icon { width: 36px; height: 36px; border-radius: 10px; background: #e0e7ff; color: #3730a3; display: flex; align-items: center; justify-content: center; font-size: 18px; }
  
  @media (max-width: 520px) { .kv-row { grid-template-columns: 1fr; } }
</style>
</head>
<body class="app-page profile-page">

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
  <h2>My Profile</h2>
  <p class="subtitle">Your staff information, biometric devices, and account settings</p>

  <div class="profile-wrap">
    <!-- Main Profile Card -->
    <div class="profile-card">
      <div class="profile-head">
        <?php if (!empty($staff['photo']) && str_starts_with($staff['photo'], 'data:image')): ?>
          <img class="profile-pic" src="<?php echo htmlspecialchars($staff['photo']); ?>" alt="Profile photo">
        <?php else: ?>
          <div class="profile-pic" style="display:flex;align-items:center;justify-content:center;color:#e2e8f0;font-weight:800;">?</div>
        <?php endif; ?>
        <div>
          <p class="profile-name"><?php echo htmlspecialchars($staff['full_name']); ?></p>
          <p class="profile-sub"><?php echo htmlspecialchars($staff['staff_id']); ?><?php echo !empty($staff['job_title']) ? ' · ' . htmlspecialchars($staff['job_title']) : ''; ?></p>
        </div>
      </div>

      <div class="kv">
        <div class="kv-row"><span>Department</span><b><?php echo htmlspecialchars($staff['department'] ?: '—'); ?></b></div>
        <div class="kv-row"><span>Branch</span><b><?php echo htmlspecialchars($staff['branch'] ?: '—'); ?></b></div>
        <div class="kv-row"><span>Email</span><b><?php echo htmlspecialchars($staff['email'] ?: '—'); ?></b></div>
        <div class="kv-row"><span>Phone</span><b><?php echo htmlspecialchars($staff['phone'] ?: '—'); ?></b></div>
      </div>

      <div class="actions">
        <a class="btn primary" href="dashboard.php">🏠 Back to Dashboard</a>
        <a class="btn" href="my_attendance.php">🕒 My Attendance</a>
        <a class="btn" href="my_report.php">📊 My Report</a>
        <a class="btn" href="change_staff_password.php">🔒 Change Password</a>
      </div>
    </div>

    <!-- Biometric Devices Card -->
    <div class="profile-card">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
          <h3 style="margin:0;font-size:17px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;">
            <span>👆</span> Enrolled Biometric Devices (Thumbprint / Passkeys)
          </h3>
          <p style="margin:4px 0 0;color:var(--text-muted);font-size:13px;">
            Enrolled Android phones and PCs with fingerprint sensors for touchless attendance clocking.
          </p>
        </div>
        <button type="button" onclick="enrollNewBiometric()" class="btn primary" style="padding:10px 16px;font-size:13px;">
          ➕ Register This Device
        </button>
      </div>

      <?php if (empty($bio_devices)): ?>
        <div style="margin-top:16px;padding:18px;background:var(--surface-alt);border:1px dashed var(--border);border-radius:14px;text-align:center;color:var(--text-muted);font-size:13.5px;font-weight:600;">
          No fingerprint devices registered yet. Click <strong>"Register This Device"</strong> above to enable native thumbprint clocking.
        </div>
      <?php else: ?>
        <ul class="bio-list">
          <?php foreach ($bio_devices as $bd): ?>
            <li class="bio-item">
              <div class="bio-item-info">
                <div class="bio-item-icon">📱</div>
                <div>
                  <div style="font-weight:700;font-size:14px;color:var(--text);"><?php echo htmlspecialchars($bd['device_name']); ?></div>
                  <div style="font-size:11.5px;color:var(--text-muted);">Enrolled on <?php echo date('M d, Y h:i A', strtotime($bd['created_at'])); ?></div>
                </div>
              </div>
              <form method="POST" onsubmit="return confirm('Remove this biometric device?');" style="margin:0;">
                <input type="hidden" name="delete_bio_id" value="<?php echo $bd['id']; ?>">
                <button type="submit" style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;padding:6px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;">
                  Remove
                </button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="footer">&copy; <?php echo date("Y"); ?> Attendance System</div>
</div>

<script src="/asset/js/biometric.js?v=<?php echo time(); ?>"></script>
<script>
async function enrollNewBiometric() {
    try {
        const supported = await TDSBiometric.isSupported();
        if (!supported) {
            alert("Biometric scanning is not supported on this browser/device. Please use Google Chrome or Microsoft Edge on an Android phone or fingerprint-enabled laptop.");
            return;
        }
        const res = await TDSBiometric.register();
        alert("🎉 " + (res.message || "Fingerprint enrolled successfully!"));
        location.reload();
    } catch (err) {
        alert("❌ Registration failed: " + err.message);
    }
}
</script>
</body>
</html>
