<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_staff = isset($_SESSION['staff_id']) && !isset($_SESSION['admin_id']);
$is_admin = isset($_SESSION['admin_id']);
$user_display_name = $_SESSION['admin'] ?? ($_SESSION['staff_name'] ?? ($is_staff ? 'Staff Member' : 'Admin'));
$user_role_label = $is_admin ? 'Administrator' : 'Staff Employee';
?>
<!-- ──────────────────────────────────────────────────────────── -->
<!-- MOBILE APP TOP HEADER (Only on screens <= 768px)               -->
<!-- ──────────────────────────────────────────────────────────── -->
<header class="mobile-app-header" id="mobileAppHeader">
    <div class="mobile-header-left">
        <a href="dashboard.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo" class="mobile-header-logo">
            <div>
                <div class="mobile-header-title">TDS Attendance</div>
                <div class="mobile-header-subtitle"><?php echo htmlspecialchars($user_role_label); ?></div>
            </div>
        </a>
    </div>
    <div class="mobile-header-actions">
        <button class="mobile-header-btn" id="mobileHeaderInstallBtn" type="button" title="Install App on Device" aria-label="Install App" style="display:none; background:#10439f; color:#ffffff;">
            <span class="t-icon">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </span>
        </button>
        <button class="mobile-header-btn" id="mobileThemeToggle" type="button" title="Toggle Theme" aria-label="Toggle Theme">
            <span class="t-icon" id="mobileThemeIcon">
                <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </span>
        </button>
        <button class="mobile-header-btn" id="mobileDrawerOpenBtn" type="button" title="Open Navigation Menu" aria-label="Open Navigation Menu">
            <span class="t-icon">
                <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </span>
        </button>
    </div>
</header>

<!-- Dark Overlay Backdrop for Mobile Drawer -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ──────────────────────────────────────────────────────────── -->
<!-- APP SIDEBAR / MOBILE DRAWER                                    -->
<!-- ──────────────────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Main Navigation">
    <!-- Desktop Brand Logo -->
    <div class="sidebar-brand desktop-brand" style="background: #ffffff; border-radius: 16px; padding: 14px 16px; margin: 0 18px 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.22); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.08);">
        <img src="/asset/img/tds_logo.png" alt="TDS Logo" style="max-height: 56px; max-width: 100%; width: auto; object-fit: contain; display: block;">
    </div>

    <!-- Desktop App Install Trigger -->
    <div class="desktop-brand" style="margin: 0 18px 16px;">
        <button type="button" id="desktopInstallAppBtn" class="app-install-trigger" style="width:100%; display:none; align-items:center; justify-content:center; gap:8px; background:linear-gradient(135deg, #10439f, #1e40af); color:#ffffff; padding:11px 14px; border-radius:12px; border:1.5px solid rgba(255,255,255,0.2); font-weight:700; font-size:13px; cursor:pointer; box-shadow:0 4px 14px rgba(16,67,159,0.35); transition:all .2s ease;">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <span>📲 Install App</span>
        </button>
        <div id="desktopAppInstalledBadge" style="display:none; text-align:center; font-size:11px; font-weight:700; color:#10b981; background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.25); border-radius:8px; padding:6px 8px;">
            ✓ App Installed (Desktop)
        </div>
    </div>

    <!-- Mobile Drawer Header (User Avatar + Close Button) -->
    <div class="sidebar-drawer-header">
        <div class="sidebar-drawer-user">
            <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-light)); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 18px; box-shadow: 0 4px 14px rgba(79,70,229,0.35);">
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
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                </span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="my_attendance.php" class="nav-item <?php echo $current_page == 'my_attendance.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
                </span>
                <span class="nav-text">My Attendance</span>
            </a>
            <a href="my_report.php" class="nav-item <?php echo $current_page == 'my_report.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </span>
                <span class="nav-text">My Report</span>
            </a>
            <a href="my_profile.php" class="nav-item <?php echo $current_page == 'my_profile.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <span class="nav-text">My Profile</span>
            </a>
            <a href="change_staff_password.php" class="nav-item <?php echo $current_page == 'change_staff_password.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1"/></svg>
                </span>
                <span class="nav-text">Change Password</span>
            </a>
        <?php elseif ($is_admin): ?>
            <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                </span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="employees.php" class="nav-item <?php echo $current_page == 'employees.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-3-3.87"/><path d="M7 21v-2a4 4 0 0 1 3-3.87"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><circle cx="17" cy="7" r="4"/></svg>
                </span>
                <span class="nav-text">Employees</span>
            </a>
            <a href="attendance.php" class="nav-item <?php echo $current_page == 'attendance.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 14l2 2 4-4"/></svg>
                </span>
                <span class="nav-text">Attendance Logs</span>
            </a>
            <a href="reports.php" class="nav-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><polyline points="3 8 9 2 15 8"/></svg>
                </span>
                <span class="nav-text">Reports</span>
            </a>
            <a href="manage_branches.php" class="nav-item <?php echo $current_page == 'manage_branches.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </span>
                <span class="nav-text">Branches &amp; Geofencing</span>
            </a>
            <a href="device_settings.php" class="nav-item <?php echo $current_page == 'device_settings.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </span>
                <span class="nav-text">Devices</span>
            </a>
            <a href="user.php" class="nav-item <?php echo $current_page == 'user.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="10" r="3"/></svg>
                </span>
                <span class="nav-text">Users</span>
            </a>
            <a href="recycle_bin.php" class="nav-item <?php echo $current_page == 'recycle_bin.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </span>
                <span class="nav-text">Recycle Bin</span>
            </a>
            <div class="nav-divider"></div>
            <a href="settings.php" class="nav-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                </span>
                <span class="nav-text">System Settings</span>
            </a>
            <a href="change_password.php" class="nav-item <?php echo $current_page == 'change_password.php' ? 'active' : ''; ?>" data-mobile-nav-link>
                <span class="t-icon nav-icon">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1"/></svg>
                </span>
                <span class="nav-text">Change Password</span>
            </a>
        <?php endif; ?>

        <div class="nav-footer">
            <div class="nav-divider"></div>
            <a href="#" class="nav-item" id="darkModeToggle">
                <span class="t-icon nav-icon" id="sidebarThemeIcon">
                    <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </span>
                <span class="nav-text" id="sidebarThemeText">Dark Mode</span>
            </a>
            <div class="mobile-drawer-install-wrap" style="margin: 8px 12px 10px;">
                <button type="button" id="mobileDrawerInstallBtn" class="app-install-trigger" style="width:100%; display:none; align-items:center; justify-content:center; gap:8px; background:linear-gradient(135deg, #10439f, #1e40af); color:#ffffff; padding:11px 14px; border-radius:12px; border:1.5px solid rgba(255,255,255,0.2); font-weight:700; font-size:13px; cursor:pointer; box-shadow:0 4px 14px rgba(16,67,159,0.35);">
                    <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>📲 Install App</span>
                </button>
            </div>
            <a href="/api/logout.php" class="nav-item nav-logout" data-mobile-nav-link style="background: rgba(239, 68, 68, 0.12); color: #f87171 !important; margin-top: 10px; font-weight: 700; border: 1px solid rgba(239, 68, 68, 0.22); border-radius: 12px;">
                <span class="t-icon nav-icon" style="color: #ef4444;">
                    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </span>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </nav>
</aside>

<!-- iOS Safari Add to Home Screen Instructions Modal -->
<div id="iosInstallModal" class="ios-install-modal" style="display:none; position:fixed; inset:0; background:rgba(6,13,31,0.85); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); z-index:9999; align-items:center; justify-content:center; padding:20px;">
    <div style="background:#ffffff; color:#0f172a; border-radius:24px; padding:26px 22px; max-width:380px; width:100%; box-shadow:0 20px 40px rgba(0,0,0,0.35); text-align:center; position:relative;">
        <button type="button" onclick="closeIosInstallModal()" style="position:absolute; top:14px; right:16px; background:transparent; border:none; font-size:24px; color:#64748b; cursor:pointer;">&times;</button>
        <div style="width:60px; height:60px; background:#e0e7ff; border-radius:16px; margin:0 auto 14px; display:flex; align-items:center; justify-content:center;">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo" style="width:42px; height:42px; object-fit:contain;">
        </div>
        <h3 style="font-size:18px; font-weight:800; color:#0f172a; margin-bottom:6px;">Install TDS Attendance App</h3>
        <p style="font-size:13px; color:#64748b; line-height:1.45; margin-bottom:16px;">Add this app to your iPhone/iPad Home Screen for fast, standalone access:</p>
        <div style="text-align:left; background:#f8fafc; border-radius:12px; padding:12px 14px; margin-bottom:18px; font-size:13px; line-height:1.7; color:#334155; border:1px solid #e2e8f0;">
            1. Tap the <strong>Share</strong> button <span style="font-size:16px;">⎋</span> at the bottom of Safari<br>
            2. Scroll down &amp; tap <strong>➕ Add to Home Screen</strong><br>
            3. Tap <strong>Add</strong> in the top-right corner
        </div>
        <button type="button" onclick="closeIosInstallModal()" style="width:100%; padding:11px; background:#10439f; color:#ffffff; border:none; border-radius:12px; font-weight:700; cursor:pointer;">Got It</button>
    </div>
</div>

<!-- ──────────────────────────────────────────────────────────── -->
<!-- NATIVE MOBILE BOTTOM NAVIGATION BAR                            -->
<!-- ──────────────────────────────────────────────────────────── -->
<nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Mobile Navigation">
    <?php if ($is_staff): ?>
        <a href="dashboard.php" class="bottom-nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <div class="b-icon">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
            </div>
            <span>Home</span>
        </a>
        <a href="my_attendance.php" class="bottom-nav-item <?php echo $current_page == 'my_attendance.php' ? 'active' : ''; ?>">
            <div class="b-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
            </div>
            <span>Attendance</span>
        </a>
        <a href="dashboard.php#clockSection" class="bottom-nav-item clock-highlight">
            <div class="clock-circle" title="Punch Clock" aria-label="Punch Clock">
                <svg viewBox="0 0 24 24" width="26" height="26" stroke="#ffffff" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <span>Clock In</span>
        </a>
        <a href="my_report.php" class="bottom-nav-item <?php echo $current_page == 'my_report.php' ? 'active' : ''; ?>">
            <div class="b-icon">
                <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </div>
            <span>Report</span>
        </a>
        <a href="javascript:void(0)" class="bottom-nav-item" id="mobileMoreBtn">
            <div class="b-icon">
                <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </div>
            <span>More</span>
        </a>
    <?php elseif ($is_admin): ?>
        <a href="dashboard.php" class="bottom-nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <div class="b-icon">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
            </div>
            <span>Home</span>
        </a>
        <a href="employees.php" class="bottom-nav-item <?php echo $current_page == 'employees.php' ? 'active' : ''; ?>">
            <div class="b-icon">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-3-3.87"/><path d="M7 21v-2a4 4 0 0 1 3-3.87"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><circle cx="17" cy="7" r="4"/></svg>
            </div>
            <span>Staff</span>
        </a>
        <a href="attendance.php" class="bottom-nav-item <?php echo $current_page == 'attendance.php' ? 'active' : ''; ?>">
            <div class="b-icon">
                <svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 14l2 2 4-4"/></svg>
            </div>
            <span>Logs</span>
        </a>
        <a href="reports.php" class="bottom-nav-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
            <div class="b-icon">
                <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><polyline points="3 8 9 2 15 8"/></svg>
            </div>
            <span>Reports</span>
        </a>
        <a href="javascript:void(0)" class="bottom-nav-item" id="mobileMoreBtn">
            <div class="b-icon">
                <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </div>
            <span>More</span>
        </a>
    <?php endif; ?>
</nav>

<!-- Audio Feedback Engine -->
<script src="/asset/js/attendance-audio.js" defer></script>

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

    // Dark Mode Controller with SVG Icons & Smooth Transition
    const themeToggles = [
        document.getElementById('darkModeToggle'),
        document.getElementById('mobileThemeToggle')
    ];

    const sunSvg = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
    const moonSvg = '<svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

    function applyTheme(isDark) {
        if (isDark) {
            document.body.classList.add('dark-mode');
            const sideIcon = document.getElementById('sidebarThemeIcon');
            const sideText = document.getElementById('sidebarThemeText');
            const mobIcon = document.getElementById('mobileThemeIcon');
            if (sideIcon) sideIcon.innerHTML = sunSvg;
            if (sideText) sideText.textContent = 'Light Mode';
            if (mobIcon) mobIcon.innerHTML = sunSvg;
        } else {
            document.body.classList.remove('dark-mode');
            const sideIcon = document.getElementById('sidebarThemeIcon');
            const sideText = document.getElementById('sidebarThemeText');
            const mobIcon = document.getElementById('mobileThemeIcon');
            if (sideIcon) sideIcon.innerHTML = moonSvg;
            if (sideText) sideText.textContent = 'Dark Mode';
            if (mobIcon) mobIcon.innerHTML = moonSvg;
        }
    }

    // Initialize Theme from localStorage or system preference
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        applyTheme(true);
    } else {
        applyTheme(false);
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

    // ────────────────────────────────────────────────────────────
    // UNIVERSAL PWA APP INSTALL CONTROLLER (Desktop, Laptop, Android, iOS)
    // ────────────────────────────────────────────────────────────
    let deferredInstallPrompt = null;
    const desktopInstallBtn = document.getElementById('desktopInstallAppBtn');
    const mobileHeaderInstallBtn = document.getElementById('mobileHeaderInstallBtn');
    const mobileDrawerInstallBtn = document.getElementById('mobileDrawerInstallBtn');
    const desktopInstalledBadge = document.getElementById('desktopAppInstalledBadge');
    const iosModal = document.getElementById('iosInstallModal');

    const isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    function showInstallTriggers() {
        if (isStandalone) {
            if (desktopInstalledBadge) desktopInstalledBadge.style.display = 'block';
            if (desktopInstallBtn) desktopInstallBtn.style.display = 'none';
            if (mobileHeaderInstallBtn) mobileHeaderInstallBtn.style.display = 'none';
            if (mobileDrawerInstallBtn) mobileDrawerInstallBtn.style.display = 'none';
            return;
        }

        if (desktopInstallBtn) desktopInstallBtn.style.display = 'inline-flex';
        if (mobileHeaderInstallBtn) mobileHeaderInstallBtn.style.display = 'inline-flex';
        if (mobileDrawerInstallBtn) mobileDrawerInstallBtn.style.display = 'inline-flex';
    }

    // Capture native PWA install prompt (Chrome, Edge on Windows, Mac, Linux, Android)
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredInstallPrompt = e;
        showInstallTriggers();
    });

    // If on iOS or non-standalone browser, show install triggers
    if (isIos && !isStandalone) {
        showInstallTriggers();
    } else if (!isStandalone) {
        // Show button on laptops/PCs and mobile so users know it can be installed
        setTimeout(showInstallTriggers, 800);
    } else {
        showInstallTriggers();
    }

    async function handleInstallClick(e) {
        if (e) e.preventDefault();
        
        if (isIos && !isStandalone) {
            if (iosModal) iosModal.style.display = 'flex';
            return;
        }

        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            const choiceResult = await deferredInstallPrompt.userChoice;
            if (choiceResult && choiceResult.outcome === 'accepted') {
                deferredInstallPrompt = null;
                if (desktopInstallBtn) desktopInstallBtn.style.display = 'none';
                if (mobileHeaderInstallBtn) mobileHeaderInstallBtn.style.display = 'none';
                if (mobileDrawerInstallBtn) mobileDrawerInstallBtn.style.display = 'none';
                if (desktopInstalledBadge) desktopInstalledBadge.style.display = 'block';
            }
        } else {
            // Inform user how to install directly from browser bar
            alert("To install this app on your computer or phone:\n\n• Laptop / PC (Chrome or Edge): Click the Install icon (⊕) in the browser address bar.\n• Android: Tap the three dots (⋮) and tap 'Install app' or 'Add to Home screen'.\n• iPhone / iPad: Tap Share (⎋) in Safari and tap 'Add to Home Screen'.");
        }
    }

    [desktopInstallBtn, mobileHeaderInstallBtn, mobileDrawerInstallBtn].forEach(btn => {
        if (btn) btn.addEventListener('click', handleInstallClick);
    });

    window.closeIosInstallModal = function() {
        if (iosModal) iosModal.style.display = 'none';
    };

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        if (desktopInstallBtn) desktopInstallBtn.style.display = 'none';
        if (mobileHeaderInstallBtn) mobileHeaderInstallBtn.style.display = 'none';
        if (mobileDrawerInstallBtn) mobileDrawerInstallBtn.style.display = 'none';
        if (desktopInstalledBadge) desktopInstalledBadge.style.display = 'block';
    });

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