<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_staff = isset($_SESSION['staff_id']) && !isset($_SESSION['admin_id']);
$is_admin = isset($_SESSION['admin_id']);
$user_display_name = $_SESSION['admin'] ?? ($_SESSION['staff_name'] ?? ($is_staff ? 'Staff Member' : 'Admin'));
$user_role_label = $is_admin ? 'Administrator' : 'Staff Employee';
?>
<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<!-- MOBILE APP TOP HEADER (Only on screens <= 768px)               -->
<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<header class="mobile-app-header" id="mobileAppHeader">
    <div class="mobile-header-left">
        <a href="dashboard.php" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo" class="mobile-header-logo">
            <div>
                <div class="mobile-header-title">TDS Attendance</div>
                <div class="mobile-header-subtitle"><?php echo htmlspecialchars($user_role_label); ?></div>
            </div>
        </a>
    </div>
    <div class="mobile-header-actions">
        <button class="mobile-header-btn" id="mobileThemeToggle" type="button" title="Toggle Theme" aria-label="Toggle Theme">🌙</button>
        <button class="mobile-header-btn" id="mobileDrawerOpenBtn" type="button" title="Open Navigation Menu" aria-label="Open Navigation Menu">☰</button>
    </div>
</header>

<!-- Dark Overlay Backdrop for Mobile Drawer -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<!-- APP SIDEBAR / MOBILE DRAWER                                    -->
<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Main Navigation">
    <!-- Desktop Brand Logo -->
    <div class="sidebar-brand desktop-brand" style="background: #ffffff; border-radius: 16px; padding: 14px 12px; margin: 0 20px 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.08);">
        <img src="/asset/img/tds_logo.png" alt="TDS Logo" style="max-height: 44px; max-width: 100%; width: auto; object-fit: contain; display: block;">
    </div>

    <!-- Mobile Drawer Header (User Avatar + Close Button) -->
    <div class="sidebar-drawer-header">
        <div class="sidebar-drawer-user">
            <div style="width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-light)); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 18px; box-shadow: 0 4px 12px rgba(79,70,229,0.3);">
                <?php echo strtoupper(substr($user_display_name, 0, 1)); ?>
            </div>
            <div>
                <div class="sidebar-drawer-name"><?php echo htmlspecialchars($user_display_name); ?></div>
                <div class="sidebar-drawer-role"><?php echo htmlspecialchars($user_role_label); ?></div>
            </div>
        </div>
        <button class="sidebar-close-btn" id="sidebarCloseBtn" type="button" aria-label="Close menu">&times;</button>
    </div>

    <nav class="sidebar-nav" aria-label="Navigation Menu">
        <?php if ($is_staff): ?>
            <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">ðŸ </span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="my_attendance.php" class="nav-item <?php echo $current_page == 'my_attendance.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">🕒</span>
                <span class="nav-text">My Attendance</span>
            </a>
            <a href="my_report.php" class="nav-item <?php echo $current_page == 'my_report.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">📊</span>
                <span class="nav-text">My Report</span>
            </a>
            <a href="my_profile.php" class="nav-item <?php echo $current_page == 'my_profile.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">👤</span>
                <span class="nav-text">My Profile</span>
            </a>
            <a href="change_staff_password.php" class="nav-item <?php echo $current_page == 'change_staff_password.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">ðŸ”’</span>
                <span class="nav-text">Change Password</span>
            </a>
        <?php elseif ($is_admin): ?>
            <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="employees.php" class="nav-item <?php echo $current_page == 'employees.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">👥</span>
                <span class="nav-text">Employees</span>
            </a>
            <a href="attendance.php" class="nav-item <?php echo $current_page == 'attendance.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">📋</span>
                <span class="nav-text">Attendance Logs</span>
            </a>
            <a href="reports.php" class="nav-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">📈</span>
                <span class="nav-text">Reports</span>
            </a>
            <a href="manage_branches.php" class="nav-item <?php echo $current_page == 'manage_branches.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">ðŸ“</span>
                <span class="nav-text">Branches & Geofencing</span>
            </a>
            <a href="device_settings.php" class="nav-item <?php echo $current_page == 'device_settings.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">ðŸ“¡</span>
                <span class="nav-text">Devices</span>
            </a>
            <a href="user.php" class="nav-item <?php echo $current_page == 'user.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">ðŸ‘‘</span>
                <span class="nav-text">Users</span>
            </a>
            <a href="recycle_bin.php" class="nav-item <?php echo $current_page == 'recycle_bin.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">🗑️ï¸</span>
                <span class="nav-text">Recycle Bin</span>
            </a>
            <div class="nav-divider"></div>
            <a href="settings.php" class="nav-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">⚙️ï¸</span>
                <span class="nav-text">System Settings</span>
            </a>
            <a href="change_password.php" class="nav-item <?php echo $current_page == 'change_password.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">ðŸ”’</span>
                <span class="nav-text">Change Password</span>
            </a>
        <?php endif; ?>

        <div class="nav-footer">
            <div class="nav-divider"></div>
            <a href="#" class="nav-item" id="darkModeToggle">
                <span class="nav-icon">🌙</span>
                <span class="nav-text">Dark Mode</span>
            </a>
            <a href="/api/logout.php" class="nav-item" data-mobile-nav-link style="background: rgba(220, 38, 38, 0.12); color: #ef4444 !important; margin-top: 10px; font-weight: 700;">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </nav>
</aside>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<!-- NATIVE MOBILE BOTTOM NAVIGATION BAR                            -->
<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Mobile Navigation">
    <?php if ($is_staff): ?>
        <a href="dashboard.php" class="bottom-nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <div class="b-icon">ðŸ </div>
            <span>Home</span>
        </a>
        <a href="my_attendance.php" class="bottom-nav-item <?php echo $current_page == 'my_attendance.php' ? 'active' : ''; ?>">
            <div class="b-icon">🕒</div>
            <span>Attendance</span>
        </a>
        <a href="dashboard.php#clockingArea" class="bottom-nav-item clock-highlight" id="mobileQuickClockBtn">
            <div class="clock-circle">âš¡</div>
            <span>Clock In</span>
        </a>
        <a href="my_report.php" class="bottom-nav-item <?php echo $current_page == 'my_report.php' ? 'active' : ''; ?>">
            <div class="b-icon">📊</div>
            <span>Report</span>
        </a>
        <a href="javascript:void(0)" class="bottom-nav-item" id="mobileMoreBtn">
            <div class="b-icon">☰</div>
            <span>More</span>
        </a>
    <?php elseif ($is_admin): ?>
        <a href="dashboard.php" class="bottom-nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <div class="b-icon">📊</div>
            <span>Home</span>
        </a>
        <a href="employees.php" class="bottom-nav-item <?php echo $current_page == 'employees.php' ? 'active' : ''; ?>">
            <div class="b-icon">👥</div>
            <span>Staff</span>
        </a>
        <a href="attendance.php" class="bottom-nav-item <?php echo $current_page == 'attendance.php' ? 'active' : ''; ?>">
            <div class="b-icon">📋</div>
            <span>Logs</span>
        </a>
        <a href="reports.php" class="bottom-nav-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
            <div class="b-icon">📈</div>
            <span>Reports</span>
        </a>
        <a href="javascript:void(0)" class="bottom-nav-item" id="mobileMoreBtn">
            <div class="b-icon">☰</div>
            <span>More</span>
        </a>
    <?php endif; ?>
</nav>

<script>
(function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const drawerOpenBtn = document.getElementById('mobileDrawerOpenBtn');
    const drawerCloseBtn = document.getElementById('sidebarCloseBtn');
    const moreBtn = document.getElementById('mobileMoreBtn');
    const navLinks = document.querySelectorAll('[data-mobile-nav-link]');

    function isMobile() {
        return window.innerWidth <= 768;
    }

    function openDrawer() {
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.classList.add('mobile-menu-open');
    }

    function closeDrawer() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('mobile-menu-open');
    }

    function toggleDrawer() {
        if (sidebar && sidebar.classList.contains('open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    }

    if (drawerOpenBtn) drawerOpenBtn.addEventListener('click', toggleDrawer);
    if (drawerCloseBtn) drawerCloseBtn.addEventListener('click', closeDrawer);
    if (moreBtn) moreBtn.addEventListener('click', toggleDrawer);
    if (overlay) overlay.addEventListener('click', closeDrawer);

    navLinks.forEach((link) => {
        link.addEventListener('click', () => {
            if (isMobile()) closeDrawer();
        });
    });

    window.addEventListener('resize', () => {
        if (!isMobile()) closeDrawer();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeDrawer();
    });

    // Dark Mode Controller
    const themeToggles = [
        document.getElementById('darkModeToggle'),
        document.getElementById('mobileThemeToggle')
    ];

    function applyTheme(isDark) {
        if (isDark) {
            document.body.classList.add('dark-mode');
            themeToggles.forEach(btn => {
                if (!btn) return;
                const icon = btn.querySelector('.nav-icon') || btn;
                const text = btn.querySelector('.nav-text');
                if (icon) icon.textContent = '☀️ï¸';
                if (text) text.textContent = 'Light Mode';
            });
        } else {
            document.body.classList.remove('dark-mode');
            themeToggles.forEach(btn => {
                if (!btn) return;
                const icon = btn.querySelector('.nav-icon') || btn;
                const text = btn.querySelector('.nav-text');
                if (icon) icon.textContent = '🌙';
                if (text) text.textContent = 'Dark Mode';
            });
        }
    }

    // Initialize Theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        applyTheme(true);
    }

    themeToggles.forEach(btn => {
        if (!btn) return;
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const isDark = !document.body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            applyTheme(isDark);
        });
    });

    window.toggleMobileDrawer = toggleDrawer;
    window.closeMobileDrawer = closeDrawer;

    // Service Worker Registration for PWA Support
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch((err) => {
                console.log('PWA Service Worker registration skipped: ', err);
            });
        });
    }
})();
</script>
