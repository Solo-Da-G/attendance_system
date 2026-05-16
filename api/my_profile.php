<?php
include(__DIR__ . "/../includes/config.php");

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile</title>
<link rel="stylesheet" href="/asset/css/style.css">
<style>
  body { background: var(--bg); }
  .profile-wrap { display: grid; grid-template-columns: 1fr; gap: 24px; margin-top: 18px; }
  .profile-card { background: var(--surface); border: 1px solid rgba(226, 232, 240, 0.6); border-radius: var(--radius-xl); box-shadow: var(--shadow-lg); padding: 28px; }
  .profile-head { display: flex; align-items: center; gap: 16px; }
  .profile-pic { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(99, 102, 241, .35); background: #111827; }
  .profile-name { font-size: 20px; font-weight: 800; margin: 0; }
  .profile-sub { margin: 2px 0 0; color: var(--text-muted); font-weight: 600; }
  .kv { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 18px; }
  .kv-row { display: grid; grid-template-columns: 160px 1fr; gap: 12px; padding: 10px 12px; background: var(--surface-alt); border: 1px solid var(--border); border-radius: 14px; }
  .kv-row span { color: var(--text-muted); font-weight: 600; }
  .kv-row b { color: var(--text); font-weight: 700; }
  .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 18px; }
  .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 16px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface); font-weight: 700; color: var(--text); box-shadow: var(--shadow-sm); }
  .btn.primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: #fff; border-color: transparent; }
  @media (max-width: 520px) { .kv-row { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<div class="content">
  <h2>My Profile</h2>
  <p class="subtitle">Your staff information and account settings</p>

  <div class="profile-wrap">
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
  </div>

  <div class="footer">&copy; <?php echo date("Y"); ?> Attendance System</div>
</div>

</body>
</html>

