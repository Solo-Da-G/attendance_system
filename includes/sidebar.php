<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine current page for active highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$is_staff = !empty($_SESSION['staff_id']) || $role === 'staff';
$is_admin = in_array($role, ['admin', 'super_admin'], true);
?>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="mobile-header">
  <h2>📋 Attendance</h2>
  <button class="menu-toggle" id="menuToggle">☰</button>
</div>

<div class="sidebar" id="mainSidebar">
  <div>
    <h2>📋 Attendance</h2>
    <nav>
      <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">🏠 Dashboard</a>
      <?php if ($is_staff): ?>
        <a href="my_attendance.php" class="<?php echo ($current_page == 'my_attendance.php') ? 'active' : ''; ?>">🕒 My Attendance</a>
        <a href="my_report.php" class="<?php echo ($current_page == 'my_report.php') ? 'active' : ''; ?>">📊 My Report</a>
        <a href="my_profile.php" class="<?php echo ($current_page == 'my_profile.php') ? 'active' : ''; ?>">👤 My Profile</a>
      <?php endif; ?>
      <?php if ($is_admin): ?>
        <a href="employees.php" class="<?php echo ($current_page == 'employees.php') ? 'active' : ''; ?>">👥 Employees</a>
        <a href="attendance.php" class="<?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">🕒 Attendance</a>
        <a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">📊 Reports</a>
        <a href="settings.php" class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">⚙️ Settings</a>
        <a href="device_settings.php" class="<?php echo ($current_page == 'device_settings.php') ? 'active' : ''; ?>">📡 Devices</a>
        <a href="manage_branches.php" class="<?php echo ($current_page == 'manage_branches.php') ? 'active' : ''; ?>">📍 Branches</a>
      <?php endif; ?>
      <?php if ($role === 'super_admin'): ?>
        <a href="user.php" class="<?php echo ($current_page == 'user.php') ? 'active' : ''; ?>">🔑 Users</a>
        <a href="recycle_bin.php" class="<?php echo ($current_page == 'recycle_bin.php') ? 'active' : ''; ?>">🗑️ Recycle Bin</a>
      <?php endif; ?>
    </nav>
  </div>
  <a href="logout.php" class="logout">🚪 Logout</a>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const toggleBtn = document.getElementById('menuToggle');
  const sidebar = document.getElementById('mainSidebar');
  const overlay = document.getElementById('sidebarOverlay');

  if(toggleBtn) {
    toggleBtn.addEventListener('click', function() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
    });
  }
  if(overlay) {
    overlay.addEventListener('click', function() {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    });
  }
});
</script>
