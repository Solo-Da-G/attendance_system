<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine current page for active highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
  <div>
    <h2>📋 Attendance</h2>
    <nav>
      <a href="/dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">🏠 Dashboard</a>
      <a href="/employees.php" class="<?php echo ($current_page == 'employees.php') ? 'active' : ''; ?>">👥 Employees</a>
      <a href="/attendance.php" class="<?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">🕒 Attendance</a>
      <a href="/reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">📊 Reports</a>
      <a href="/settings.php" class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">⚙️ Settings</a>
      <a href="/device_settings.php" class="<?php echo ($current_page == 'device_settings.php') ? 'active' : ''; ?>">📡 Devices</a>
      <a href="/manage_branches.php" class="<?php echo ($current_page == 'manage_branches.php') ? 'active' : ''; ?>">📍 Branches</a>
      <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
        <a href="/user.php" class="<?php echo ($current_page == 'user.php') ? 'active' : ''; ?>">🔑 Users</a>
      <?php endif; ?>
    </nav>
  </div>
  <a href="/logout.php" class="logout">🚪 Logout</a>
</div>
