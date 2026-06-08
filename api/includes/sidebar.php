<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$is_staff = isset($_SESSION['staff_id']) && !isset($_SESSION['admin_id']);
$is_admin = isset($_SESSION['admin_id']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<style>
    /* Mobile Navigation Styles */
    .mobile-menu-toggle {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1001;
        background: var(--primary);
        border: none;
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 24px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 280px;
        height: 100vh;
        background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
        color: white;
        overflow-y: auto;
        transition: transform 0.3s ease;
        z-index: 1000;
    }
    
    .sidebar.closed {
        transform: translateX(-100%);
    }
    
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
    }
    
    .sidebar-overlay.active {
        display: block;
    }
    
    .content {
        margin-left: 280px;
        transition: margin-left 0.3s ease;
        width: calc(100% - 280px);
    }
    
    .content.expanded {
        margin-left: 0;
        width: 100%;
    }
    
    @media (max-width: 768px) {
        .mobile-menu-toggle {
            display: block;
        }
        
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.open {
            transform: translateX(0);
        }
        
        .content {
            margin-left: 0;
            width: 100%;
            padding-top: 70px;
        }
        
        .content.expanded {
            margin-left: 0;
        }
    }
    
    .sidebar-logo {
        padding: 30px 24px;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        margin-bottom: 24px;
    }
    
    .sidebar-logo img {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        margin-bottom: 12px;
    }
    
    .sidebar-logo h3 {
        font-size: 18px;
        margin: 0;
    }
    
    .sidebar-logo p {
        font-size: 12px;
        opacity: 0.7;
        margin: 4px 0 0;
    }
    
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 24px;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        transition: all 0.3s;
        margin: 4px 12px;
        border-radius: 12px;
    }
    
    .nav-item:hover, .nav-item.active {
        background: rgba(79, 70, 229, 0.2);
        color: white;
    }
    
    .nav-item.active {
        background: var(--primary);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }
    
    .nav-icon {
        font-size: 20px;
        width: 28px;
    }
    
    .nav-text {
        font-size: 14px;
        font-weight: 500;
    }
    
    .nav-divider {
        height: 1px;
        background: rgba(255,255,255,0.1);
        margin: 16px 24px;
    }
</style>
</head>
<body>

<button class="mobile-menu-toggle" onclick="toggleMobileMenu()">☰</button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileMenu()"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="/asset/img/miss_logo.png" alt="Logo">
        <h3>Attendance System</h3>
        <p>Secure Clock-in System</p>
    </div>
    
    <?php if ($is_staff): ?>
        <!-- Staff Navigation -->
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-text">Dashboard</span>
        </a>
        <a href="my_profile.php" class="nav-item <?php echo $current_page == 'my_profile.php' ? 'active' : ''; ?>">
            <span class="nav-icon">👤</span>
            <span class="nav-text">My Profile</span>
        </a>
        <a href="my_attendance.php" class="nav-item <?php echo $current_page == 'my_attendance.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🕒</span>
            <span class="nav-text">My Attendance</span>
        </a>
        <a href="my_report.php" class="nav-item <?php echo $current_page == 'my_report.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-text">My Report</span>
        </a>
        <a href="change_staff_password.php" class="nav-item <?php echo $current_page == 'change_staff_password.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🔒</span>
            <span class="nav-text">Change Password</span>
        </a>
        
    <?php elseif ($is_admin): ?>
        <!-- Admin Navigation -->
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Dashboard</span>
        </a>
        <a href="employees.php" class="nav-item <?php echo $current_page == 'employees.php' ? 'active' : ''; ?>">
            <span class="nav-icon">👥</span>
            <span class="nav-text">Employees</span>
        </a>
        <a href="attendance.php" class="nav-item <?php echo $current_page == 'attendance.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📋</span>
            <span class="nav-text">Attendance</span>
        </a>
        <a href="reports.php" class="nav-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📈</span>
            <span class="nav-text">Reports</span>
        </a>
        <a href="manage_branches.php" class="nav-item <?php echo $current_page == 'manage_branches.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📍</span>
            <span class="nav-text">Branches</span>
        </a>
        <a href="device_settings.php" class="nav-item <?php echo $current_page == 'device_settings.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📡</span>
            <span class="nav-text">Devices</span>
        </a>
        <a href="user.php" class="nav-item <?php echo $current_page == 'user.php' ? 'active' : ''; ?>">
            <span class="nav-icon">👑</span>
            <span class="nav-text">Users</span>
        </a>
        <div class="nav-divider"></div>
        <a href="settings.php" class="nav-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
            <span class="nav-icon">⚙️</span>
            <span class="nav-text">Settings</span>
        </a>
        <a href="change_password.php" class="nav-item <?php echo $current_page == 'change_password.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🔒</span>
            <span class="nav-text">Change Password</span>
        </a>
    <?php endif; ?>
    
<div class="nav-divider"></div>
    <a href="/api/logout.php" class="nav-item">
        <span class="nav-icon">🚪</span>
        <span class="nav-text">Logout</span>
    </a>
</div>

<script>
    function toggleMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const content = document.querySelector('.content');
        
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        
        if (sidebar.classList.contains('open')) {
            sidebar.style.transform = 'translateX(0)';
        } else {
            sidebar.style.transform = 'translateX(-100%)';
        }
    }
    
    function closeMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        sidebar.style.transform = 'translateX(-100%)';
    }
    
    // Close menu when window is resized above mobile breakpoint
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.style.transform = '';
            overlay.classList.remove('active');
        }
    });
</script>
</body>
</html>