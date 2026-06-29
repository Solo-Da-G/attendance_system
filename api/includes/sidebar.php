<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_staff = isset($_SESSION['staff_id']) && !isset($_SESSION['admin_id']);
$is_admin = isset($_SESSION['admin_id']);
?>
<style>
    .mobile-menu-toggle {
        display: none;
        position: fixed;
        top: 16px;
        left: 16px;
        z-index: 1201;
        background: var(--primary);
        border: none;
        color: #fff;
        width: 48px;
        height: 48px;
        border-radius: 14px;
        cursor: pointer;
        font-size: 22px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.25);
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        z-index: 1190;
    }

    .sidebar-overlay.active {
        display: block;
    }

    .sidebar {
        display: flex !important;
        width: 280px;
        max-width: 82vw;
        background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
        color: #fff;
        z-index: 1200;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
        padding-top: 20px;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 18px;
        color: rgba(255,255,255,0.82) !important;
        text-decoration: none;
        transition: all 0.25s ease;
        margin: 4px 12px;
        border-radius: 12px;
        border-left: none !important;
    }

    .nav-item:hover,
    .nav-item.active {
        background: rgba(79, 70, 229, 0.22);
        color: #fff !important;
    }

    .nav-item.active {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        box-shadow: 0 10px 20px rgba(79, 70, 229, 0.28);
    }

    .nav-icon {
        font-size: 20px;
        width: 28px;
        text-align: center;
        flex: 0 0 28px;
    }

    .nav-text {
        font-size: 14px;
        font-weight: 600;
    }

    .nav-divider {
        height: 1px;
        background: rgba(255,255,255,0.10);
        margin: 16px 24px;
    }

    .nav-footer {
        margin-top: auto;
        padding-bottom: 16px;
    }

    @media (max-width: 768px) {
        .mobile-menu-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.28s ease;
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .content {
            margin-left: 0 !important;
            width: 100% !important;
            padding-top: 80px !important;
        }

        body.mobile-menu-open {
            overflow: hidden;
        }
    }
</style>

<button class="mobile-menu-toggle" id="mobileMenuToggle" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">☰</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <nav class="sidebar-nav" aria-label="Dashboard navigation">
        <?php if ($is_staff): ?>
            <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">🏠</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="my_attendance.php" class="nav-item <?php echo $current_page == 'my_attendance.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">🕒</span>
                <span class="nav-text">Attendance</span>
            </a>
            <a href="my_profile.php" class="nav-item <?php echo $current_page == 'my_profile.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">👤</span>
                <span class="nav-text">Profile</span>
            </a>
            <a href="my_report.php" class="nav-item <?php echo $current_page == 'my_report.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">📊</span>
                <span class="nav-text">Report</span>
            </a>
            <a href="change_staff_password.php" class="nav-item <?php echo $current_page == 'change_staff_password.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">🔒</span>
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
                <span class="nav-text">Attendance</span>
            </a>
            <a href="reports.php" class="nav-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">📈</span>
                <span class="nav-text">Reports</span>
            </a>
            <a href="manage_branches.php" class="nav-item <?php echo $current_page == 'manage_branches.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">📍</span>
                <span class="nav-text">Branches</span>
            </a>
            <a href="device_settings.php" class="nav-item <?php echo $current_page == 'device_settings.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">📡</span>
                <span class="nav-text">Devices</span>
            </a>
            <a href="user.php" class="nav-item <?php echo $current_page == 'user.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">👑</span>
                <span class="nav-text">Users</span>
            </a>
            <a href="recycle_bin.php" class="nav-item <?php echo $current_page == 'recycle_bin.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">🗑️</span>
                <span class="nav-text">Recycle Bin</span>
            </a>
            <div class="nav-divider"></div>
            <a href="settings.php" class="nav-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">⚙️</span>
                <span class="nav-text">Settings</span>
            </a>
            <a href="change_password.php" class="nav-item <?php echo $current_page == 'change_password.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="nav-icon">🔒</span>
                <span class="nav-text">Change Password</span>
            </a>
        <?php endif; ?>

        <div class="nav-footer">
            <div class="nav-divider"></div>
            <a href="#" class="nav-item" id="darkModeToggle">
                <span class="nav-icon">🌙</span>
                <span class="nav-text">Dark Mode</span>
            </a>
            <a href="/api/logout.php" class="nav-item" data-mobile-nav-link style="background: rgba(220, 38, 38, 0.1); color: #ef4444 !important; margin-top: 10px;">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </nav>
</aside>

<style>
/* Dark Mode Variables */
body.dark-mode {
    --bg: #0f172a;
    --surface: #1e293b;
    --surface-alt: #334155;
    --border: #334155;
    --text: #f8fafc;
    --text-muted: #94a3b8;
    --shadow: 0 4px 14px rgba(0,0,0,.3);
    --shadow-sm: 0 1px 3px rgba(0,0,0,.2);
}
body.dark-mode .dashboard-header { background: linear-gradient(135deg, #020617, #0f172a); }
body.dark-mode .clocking-card, 
body.dark-mode .recent-table, 
body.dark-mode .widget-card,
body.dark-mode .settings-box { background: var(--surface); border-color: var(--border); }
body.dark-mode input, body.dark-mode select { background: var(--surface-alt); color: white; border-color: var(--border); }
body.dark-mode table { background: var(--surface); border-color: var(--border); }
body.dark-mode th { background: #334155; color: #e2e8f0; }
body.dark-mode td { border-bottom-color: var(--border); color: #cbd5e1; }
</style>

<script>
    (function () {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggle = document.getElementById('mobileMenuToggle');
        const navLinks = document.querySelectorAll('[data-mobile-nav-link]');

        if (!sidebar || !overlay || !toggle) return;

        function isMobile() {
            return window.innerWidth <= 768;
        }

        function openMobileMenu() {
            if (!isMobile()) return;
            sidebar.classList.add('open');
            overlay.classList.add('active');
            document.body.classList.add('mobile-menu-open');
            toggle.setAttribute('aria-expanded', 'true');
        }

        function closeMobileMenu() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.classList.remove('mobile-menu-open');
            toggle.setAttribute('aria-expanded', 'false');
        }

        function toggleMobileMenu() {
            if (sidebar.classList.contains('open')) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        }

        toggle.addEventListener('click', toggleMobileMenu);
        overlay.addEventListener('click', closeMobileMenu);

        navLinks.forEach((link) => {
            link.addEventListener('click', () => {
                if (isMobile()) closeMobileMenu();
            });
        });

        window.addEventListener('resize', () => {
            if (!isMobile()) {
                closeMobileMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMobileMenu();
            }
        });

        window.toggleMobileMenu = toggleMobileMenu;
        window.closeMobileMenu = closeMobileMenu;
        
        // Dark Mode Logic
        const darkModeBtn = document.getElementById('darkModeToggle');
        if (darkModeBtn) {
            // Check local storage
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark-mode');
                darkModeBtn.querySelector('.nav-icon').textContent = '☀️';
                darkModeBtn.querySelector('.nav-text').textContent = 'Light Mode';
            }
            
            darkModeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                document.body.classList.toggle('dark-mode');
                if (document.body.classList.contains('dark-mode')) {
                    localStorage.setItem('theme', 'dark');
                    darkModeBtn.querySelector('.nav-icon').textContent = '☀️';
                    darkModeBtn.querySelector('.nav-text').textContent = 'Light Mode';
                } else {
                    localStorage.setItem('theme', 'light');
                    darkModeBtn.querySelector('.nav-icon').textContent = '🌙';
                    darkModeBtn.querySelector('.nav-text').textContent = 'Dark Mode';
                }
            });
        }
    })();
</script>
