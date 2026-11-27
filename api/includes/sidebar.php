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
        <div class="mobile-header-nav-btns" style="display: flex; align-items: center; gap: 6px;">
            <button class="mobile-header-btn app-nav-btn app-back-btn" id="mobileBackBtn" type="button" onclick="appGoBack()" title="Back to Previous Page" aria-label="Go Back">
                <span class="t-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                </span>
            </button>
            <button class="mobile-header-btn app-nav-btn app-refresh-btn" id="mobileRefreshBtn" type="button" onclick="appRefreshPage(this)" title="Refresh Page" aria-label="Refresh Page">
                <span class="t-icon">
                    <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                    </svg>
                </span>
            </button>
        </div>
        <a href="dashboard.php" style="display: flex; align-items: center; gap: 8px; text-decoration: none; min-width: 0;">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo" class="mobile-header-logo">
            <div style="min-width: 0;">
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
    <div class="sidebar-brand desktop-brand" style="background: #ffffff; border-radius: 14px; padding: 10px 14px; margin: 0 14px 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.18); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.08);">
        <img src="/asset/img/tds_logo.png" alt="TDS Logo" style="max-height: 44px; max-width: 100%; width: auto; object-fit: contain; display: block;">
    </div>

    <!-- Desktop App Navigation Controls (Back & Refresh for computer installed app & browser) -->
    <div class="desktop-brand app-nav-controls-desktop" style="margin: 0 14px 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
        <button type="button" class="app-nav-pill-btn app-back-btn" id="desktopBackBtn" onclick="appGoBack()" title="Back to Previous Page (Alt+Left)" aria-label="Go Back">
            <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Back</span>
        </button>
        <button type="button" class="app-nav-pill-btn app-refresh-btn" id="desktopRefreshBtn" onclick="appRefreshPage(this)" title="Refresh Page (F5)" aria-label="Refresh Page">
            <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" fill="none" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
            </svg>
            <span>Refresh</span>
        </button>
    </div>

    <!-- Desktop App Install Trigger -->
    <div class="desktop-brand" style="margin: 0 14px 12px;">
        <button type="button" id="desktopInstallAppBtn" class="app-install-trigger" style="width:100%; display:none; align-items:center; justify-content:center; gap:8px; background:linear-gradient(135deg, #10439f, #1e40af); color:#ffffff; padding:8px 12px; border-radius:10px; border:1.5px solid rgba(255,255,255,0.2); font-weight:700; font-size:12px; cursor:pointer; box-shadow:0 3px 12px rgba(16,67,159,0.3); transition:all .2s ease;">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <span>📲 Install App</span>
        </button>
        <div id="desktopAppInstalledBadge" style="display:none; text-align:center; font-size:11px; font-weight:700; color:#10b981; background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.25); border-radius:8px; padding:5px 8px;">
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

    <!-- Mobile Drawer Quick Nav Controls -->
    <div class="mobile-drawer-nav-controls" style="margin: 0 14px 12px; display: none; gap: 8px;">
        <button type="button" class="app-nav-pill-btn app-back-btn" onclick="appGoBack()" style="flex:1;">
            <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Back</span>
        </button>
        <button type="button" class="app-nav-pill-btn app-refresh-btn" onclick="appRefreshPage(this)" style="flex:1;">
            <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" fill="none" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
            </svg>
            <span>Refresh</span>
        </button>
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
            <a href="#" class="nav-item" id="themeMoodBtn" onclick="openThemeMoodModal(event)" title="Choose Dashboard Background Color Mood">
                <span class="t-icon nav-icon" id="sidebarThemeIcon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </span>
                <span class="nav-text" id="sidebarThemeText">🎨 Theme Mood</span>
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

<!-- Global Theme Moods Selector Modal -->
<div id="themeMoodModal" class="theme-mood-modal" style="display:none; position:fixed; inset:0; background:rgba(6,13,31,0.82); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); z-index:10005; align-items:center; justify-content:center; padding:16px;">
    <div style="background:var(--surface, #ffffff); color:var(--text, #0f172a); border:1px solid var(--border, #e2e8f0); border-radius:26px; padding:30px 24px; max-width:520px; width:100%; box-shadow:0 25px 60px rgba(0,0,0,0.45); position:relative; box-sizing:border-box;">
        <button type="button" onclick="closeThemeMoodModal()" style="position:absolute; top:16px; right:18px; background:transparent; border:none; font-size:26px; color:var(--text-muted, #64748b); cursor:pointer; line-height:1;" title="Close">&times;</button>
        <div style="text-align:center; margin-bottom:20px;">
            <div style="font-size:38px; margin-bottom:6px;">🎨</div>
            <h3 style="margin:0 0 5px; font-size:21px; font-weight:800; color:var(--text, #0f172a);">Dashboard Theme Moods</h3>
            <p style="margin:0; font-size:13.5px; color:var(--text-muted, #64748b);">Choose a background color mood that suits your preference</p>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:12px; margin-bottom:22px;">
            <!-- 1. Daylight -->
            <div class="mood-card" data-mood="light" onclick="selectThemeMood('light')" style="background:#ffffff; border:2px solid #e2e8f0; border-radius:16px; padding:14px 10px; cursor:pointer; text-align:center; transition:all 0.2s;">
                <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #f8fafc, #e2e8f0); margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:18px; border:1px solid #cbd5e1;">☀️</div>
                <div style="font-size:13px; font-weight:800; color:#0f172a;">Daylight</div>
                <div style="font-size:11px; color:#64748b; margin-top:2px;">Clean Classic</div>
            </div>

            <!-- 2. Midnight -->
            <div class="mood-card" data-mood="midnight" onclick="selectThemeMood('midnight')" style="background:#090d16; border:2px solid rgba(255,255,255,0.1); border-radius:16px; padding:14px 10px; cursor:pointer; text-align:center; transition:all 0.2s;">
                <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #0f172a, #1e293b); margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:18px; border:1px solid #334155;">🌙</div>
                <div style="font-size:13px; font-weight:800; color:#ffffff;">Midnight</div>
                <div style="font-size:11px; color:#94a3b8; margin-top:2px;">Dark Space</div>
            </div>

            <!-- 3. Ocean Blue -->
            <div class="mood-card" data-mood="ocean" onclick="selectThemeMood('ocean')" style="background:#051329; border:2px solid rgba(56,189,248,0.25); border-radius:16px; padding:14px 10px; cursor:pointer; text-align:center; transition:all 0.2s;">
                <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #0284c7, #0369a1); margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:18px; border:1px solid #38bdf8;">🌊</div>
                <div style="font-size:13px; font-weight:800; color:#ffffff;">Ocean Blue</div>
                <div style="font-size:11px; color:#7dd3fc; margin-top:2px;">Deep Sapphire</div>
            </div>

            <!-- 4. Emerald Oasis -->
            <div class="mood-card" data-mood="emerald" onclick="selectThemeMood('emerald')" style="background:#031713; border:2px solid rgba(52,211,153,0.25); border-radius:16px; padding:14px 10px; cursor:pointer; text-align:center; transition:all 0.2s;">
                <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #059669, #047857); margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:18px; border:1px solid #34d399;">🌲</div>
                <div style="font-size:13px; font-weight:800; color:#ffffff;">Emerald</div>
                <div style="font-size:11px; color:#6ee7b7; margin-top:2px;">Forest Oasis</div>
            </div>

            <!-- 5. Royal Amethyst -->
            <div class="mood-card" data-mood="amethyst" onclick="selectThemeMood('amethyst')" style="background:#0e0724; border:2px solid rgba(192,132,252,0.25); border-radius:16px; padding:14px 10px; cursor:pointer; text-align:center; transition:all 0.2s;">
                <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #7c3aed, #6d28d9); margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:18px; border:1px solid #c084fc;">🔮</div>
                <div style="font-size:13px; font-weight:800; color:#ffffff;">Amethyst</div>
                <div style="font-size:11px; color:#d8b4fe; margin-top:2px;">Cyber Violet</div>
            </div>

            <!-- 6. Sunset Amber -->
            <div class="mood-card" data-mood="sunset" onclick="selectThemeMood('sunset')" style="background:#180d04; border:2px solid rgba(251,191,36,0.25); border-radius:16px; padding:14px 10px; cursor:pointer; text-align:center; transition:all 0.2s;">
                <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #d97706, #b45309); margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:18px; border:1px solid #fbbf24;">🌇</div>
                <div style="font-size:13px; font-weight:800; color:#ffffff;">Sunset</div>
                <div style="font-size:11px; color:#fcd34d; margin-top:2px;">Warm Amber</div>
            </div>
        </div>

        <div style="text-align:center;">
            <button type="button" onclick="closeThemeMoodModal()" style="padding:10px 28px; border-radius:12px; background:var(--primary, #10439f); color:white; border:none; font-weight:700; font-size:13.5px; cursor:pointer; box-shadow:0 4px 12px rgba(16,67,159,0.3);">
                Apply &amp; Done
            </button>
        </div>
    </div>
</div>
</aside>

<!-- Floating Quick Navigation Pill (For Installed Desktop App & Large Screens) -->
<div class="app-desktop-floating-nav" id="appDesktopFloatingNav" aria-label="Desktop App Navigation Controls">
    <button type="button" class="floating-nav-pill-btn app-back-btn" onclick="appGoBack()" title="Back to Previous Page (Alt+Left)" aria-label="Go Back">
        <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        <span>Back</span>
    </button>
    <div class="floating-nav-divider"></div>
    <button type="button" class="floating-nav-pill-btn app-refresh-btn" onclick="appRefreshPage(this)" title="Refresh Page (F5)" aria-label="Refresh Page">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
        </svg>
        <span>Refresh</span>
    </button>
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

    // Theme Mood & Background Controller
    window.openThemeMoodModal = function(e) {
        if (e) e.preventDefault();
        const modal = document.getElementById('themeMoodModal');
        if (modal) {
            modal.style.display = 'flex';
            updateMoodActiveCards();
        }
    };

    window.closeThemeMoodModal = function() {
        const modal = document.getElementById('themeMoodModal');
        if (modal) modal.style.display = 'none';
    };

    window.selectThemeMood = function(mood) {
        applyThemeMood(mood);
        updateMoodActiveCards();
    };

    function updateMoodActiveCards() {
        const currentMood = localStorage.getItem('tds_theme_mood') || (localStorage.getItem('theme') === 'dark' ? 'midnight' : 'light');
        document.querySelectorAll('.mood-card').forEach(card => {
            const m = card.getAttribute('data-mood');
            if (m === currentMood) {
                card.style.borderColor = '#2563eb';
                card.style.boxShadow = '0 0 0 3px rgba(37,99,235,0.4)';
            } else {
                card.style.borderColor = (m === 'light') ? '#e2e8f0' : 'rgba(255,255,255,0.15)';
                card.style.boxShadow = 'none';
            }
        });
    }

    window.applyThemeMood = function(mood) {
        document.body.classList.remove('theme-light', 'theme-midnight', 'theme-ocean', 'theme-emerald', 'theme-amethyst', 'theme-sunset', 'dark-mode');
        document.documentElement.classList.remove('theme-light', 'theme-midnight', 'theme-ocean', 'theme-emerald', 'theme-amethyst', 'theme-sunset', 'dark-mode');

        if (mood === 'light') {
            document.body.classList.add('theme-light');
            document.documentElement.classList.add('theme-light');
            localStorage.setItem('theme', 'light');
        } else {
            document.body.classList.add('dark-mode', 'theme-' + mood);
            document.documentElement.classList.add('dark-mode', 'theme-' + mood);
            localStorage.setItem('theme', 'dark');
        }
        localStorage.setItem('tds_theme_mood', mood);

        const moodLabels = {
            'light': '☀️ Daylight',
            'midnight': '🌙 Midnight',
            'ocean': '🌊 Ocean Blue',
            'emerald': '🌲 Emerald',
            'amethyst': '🔮 Amethyst',
            'sunset': '🌇 Sunset'
        };

        const sideText = document.getElementById('sidebarThemeText');
        if (sideText) sideText.textContent = moodLabels[mood] || '🎨 Theme Mood';
        const dashLabel = document.getElementById('dashMoodLabel');
        if (dashLabel) dashLabel.textContent = moodLabels[mood] || 'Theme Mood';
    };

    // Initialize Theme Mood from localStorage immediately
    const initialMood = localStorage.getItem('tds_theme_mood') || (localStorage.getItem('theme') === 'dark' ? 'midnight' : 'light');
    applyThemeMood(initialMood);

    // Close modal on click outside
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('themeMoodModal');
        if (modal && e.target === modal) {
            closeThemeMoodModal();
        }
    });

    const mobToggle = document.getElementById('mobileThemeToggle');
    if (mobToggle) {
        mobToggle.addEventListener('click', function(e) {
            e.preventDefault();
            openThemeMoodModal(e);
        });
    }

    window.toggleMobileDrawer = toggleDrawer;
    window.closeMobileDrawer = closeDrawer;

    // ────────────────────────────────────────────────────────────
    // UNIVERSAL PWA APP INSTALL CONTROLLER (Desktop, Laptop, Android, iOS)
    // ────────────────────────────────────────────────────────────
    let deferredInstallPrompt = window.TDS_InstallPrompt || null;
    const desktopInstallBtn = document.getElementById('desktopInstallAppBtn');
    const mobileHeaderInstallBtn = document.getElementById('mobileHeaderInstallBtn');
    const mobileDrawerInstallBtn = document.getElementById('mobileDrawerInstallBtn');
    const desktopInstalledBadge = document.getElementById('desktopAppInstalledBadge');

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
        window.TDS_InstallPrompt = e;
        showInstallTriggers();
    });

    window.addEventListener('tds:pwa-ready', () => {
        if (window.TDS_InstallPrompt) {
            deferredInstallPrompt = window.TDS_InstallPrompt;
            showInstallTriggers();
        }
    });

    // Show triggers if ready or available
    if (deferredInstallPrompt || isIos || !isStandalone) {
        showInstallTriggers();
    }

    async function handleInstallClick(e) {
        if (e) e.preventDefault();
        
        if (isStandalone) {
            alert("The app is already installed and running on this device!");
            return;
        }

        const promptEvt = deferredInstallPrompt || window.TDS_InstallPrompt;

        if (promptEvt) {
            try {
                promptEvt.prompt();
                const choiceResult = await promptEvt.userChoice;
                if (choiceResult && choiceResult.outcome === 'accepted') {
                    deferredInstallPrompt = null;
                    window.TDS_InstallPrompt = null;
                    if (desktopInstallBtn) desktopInstallBtn.style.display = 'none';
                    if (mobileHeaderInstallBtn) mobileHeaderInstallBtn.style.display = 'none';
                    if (mobileDrawerInstallBtn) mobileDrawerInstallBtn.style.display = 'none';
                    if (desktopInstalledBadge) desktopInstalledBadge.style.display = 'block';
                }
                return;
            } catch (err) {
                console.error("Install prompt error:", err);
            }
        }

        const isAndroid = /android/i.test(navigator.userAgent);
        const preferTab = isIos ? 'ios' : (isAndroid ? 'android' : 'desktop');
        if (typeof window.openSmartInstallModal === 'function') {
            window.openSmartInstallModal(preferTab);
        }
    }

    [desktopInstallBtn, mobileHeaderInstallBtn, mobileDrawerInstallBtn].forEach(btn => {
        if (btn) btn.addEventListener('click', handleInstallClick);
    });

    window.openSmartInstallModal = function(preferTab) {
        const modal = document.getElementById('smartAppInstallModal');
        if (!modal) return;
        const targetTab = preferTab || (isIos ? 'ios' : (/android/i.test(navigator.userAgent) ? 'android' : 'desktop'));
        if (typeof window.switchInstallTab === 'function') {
            window.switchInstallTab(targetTab);
        }
        modal.style.display = 'flex';
    };

    window.closeSmartInstallModal = function() {
        const modal = document.getElementById('smartAppInstallModal');
        if (modal) modal.style.display = 'none';
    };
    window.closeIosInstallModal = window.closeSmartInstallModal;

    window.switchInstallTab = function(tabName) {
        ['android', 'ios', 'desktop'].forEach(function(t) {
            const pane = document.getElementById('pane' + t.charAt(0).toUpperCase() + t.slice(1));
            const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
            if (pane) pane.style.display = (t === tabName) ? 'block' : 'none';
            if (btn) {
                if (t === tabName) {
                    btn.style.background = '#10439f';
                    btn.style.color = '#ffffff';
                    btn.style.boxShadow = '0 2px 8px rgba(16,67,159,0.4)';
                } else {
                    btn.style.background = 'transparent';
                    btn.style.color = '#94a3b8';
                    btn.style.boxShadow = 'none';
                }
            }
        });
    };

    // Global App Navigation Handlers (Back Arrow & Refresh Page)
    window.appGoBack = function() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = 'dashboard.php';
        }
    };

    window.appRefreshPage = function(triggerBtn) {
        if (triggerBtn) {
            triggerBtn.classList.add('is-refreshing');
            const svg = triggerBtn.querySelector('svg');
            if (svg) svg.style.animation = 'appSpin 0.7s linear infinite';
        }
        setTimeout(function() {
            window.location.reload();
        }, 60);
    };

    // Keyboard shortcut: Alt+Left Arrow for back
    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.key === 'ArrowLeft') {
            e.preventDefault();
            window.appGoBack();
        }
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        window.TDS_InstallPrompt = null;
        if (desktopInstallBtn) desktopInstallBtn.style.display = 'none';
        if (mobileHeaderInstallBtn) mobileHeaderInstallBtn.style.display = 'none';
        if (mobileDrawerInstallBtn) mobileDrawerInstallBtn.style.display = 'none';
        if (desktopInstalledBadge) desktopInstalledBadge.style.display = 'block';
    });

    // Service Worker Registration for PWA Support
    if ('serviceWorker' in navigator) {
        const registerSW = () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((err) => {
                console.log('PWA Service Worker registration skipped: ', err);
            });
        };
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            registerSW();
        } else {
            window.addEventListener('DOMContentLoaded', registerSW);
        }
    }
})();
</script>

<!-- Unified Smart App Install Modal (Android APK, Chrome PWA, iOS Safari, Desktop) -->
<div id="smartAppInstallModal" class="smart-install-modal" style="display:none; position:fixed; inset:0; background:rgba(6,13,31,0.85); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:999999; align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div class="smart-install-card" style="background:#0f172a; color:#f8fafc; border-radius:24px; padding:24px 20px; max-width:440px; width:100%; max-height:92vh; overflow-y:auto; box-shadow:0 25px 60px -15px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.12); position:relative; text-align:center; font-family:'Plus Jakarta Sans', sans-serif; box-sizing:border-box;">
        <!-- Close Button -->
        <button type="button" onclick="closeSmartInstallModal()" style="position:absolute; top:16px; right:16px; background:rgba(255,255,255,0.08); border:none; width:34px; height:34px; border-radius:50%; color:#94a3b8; font-size:22px; cursor:pointer; display:flex; align-items:center; justify-content:center; line-height:1; transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.color='#fff';" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.color='#94a3b8';">&times;</button>
        
        <!-- Header Brand Icon -->
        <div style="width:60px; height:60px; background:#ffffff; border-radius:18px; margin:0 auto 12px; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 24px rgba(16,67,159,0.35); padding:6px; box-sizing:border-box;">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo" style="width:100%; height:100%; object-fit:contain;">
        </div>
        <h3 style="font-size:18px; font-weight:800; color:#ffffff; margin:0 0 4px; letter-spacing:-0.3px;">Install TDS Attendance App</h3>
        <p style="font-size:12.5px; color:#94a3b8; margin:0 0 16px; line-height:1.4;">Fast 1-tap access on your mobile device</p>

        <!-- Segmented Platform Tabs -->
        <div style="display:flex; background:rgba(255,255,255,0.06); padding:4px; border-radius:14px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08); gap:4px;">
            <button type="button" id="tabBtnAndroid" onclick="switchInstallTab('android')" style="flex:1; padding:9px 6px; border:none; border-radius:10px; font-weight:700; font-size:12px; cursor:pointer; transition:all 0.2s; background:#10439f; color:#ffffff; display:flex; align-items:center; justify-content:center; gap:6px;">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M17.523 15.3414c-.5511 0-.9993-.4486-.9993-.9997s.4482-.9993.9993-.9993c.551 0 .9993.4482.9993.9993.0001.5511-.4482.9997-.9993.9997m-11.046 0c-.5511 0-.9993-.4486-.9993-.9997s.4482-.9993.9993-.9993c.5511 0 .9993.4482.9993.9993 0 .5511-.4482.9997-.9993.9997m11.4045-6.02l1.996-3.4572c.1123-.1944.0455-.4435-.149-.5557-.1944-.1123-.4435-.0456-.5557.1489l-2.023 3.504c-1.4883-.6803-3.1504-1.0664-4.9308-1.0664s-3.4425.3861-4.9308 1.0664l-2.023-3.504c-.1122-.1945-.3613-.2612-.5557-.1489-.1945.1122-.2613.3613-.149.5557l1.996 3.4572C4.1687 10.4283 2.5 13.0645 2.5 16.1436h19c0-3.0791-1.6687-5.7153-4.6185-6.8222"/></svg>
                Android
            </button>
            <button type="button" id="tabBtnIos" onclick="switchInstallTab('ios')" style="flex:1; padding:9px 6px; border:none; border-radius:10px; font-weight:700; font-size:12px; cursor:pointer; transition:all 0.2s; background:transparent; color:#94a3b8; display:flex; align-items:center; justify-content:center; gap:6px;">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.42c.67-.82 1.13-1.96.99-3.11-1 .04-2.14.65-2.82 1.45-.6.69-1.12 1.83-.98 2.96 1.12.09 2.19-.55 2.81-1.3"/></svg>
                iPhone
            </button>
            <button type="button" id="tabBtnDesktop" onclick="switchInstallTab('desktop')" style="flex:1; padding:9px 6px; border:none; border-radius:10px; font-weight:700; font-size:12px; cursor:pointer; transition:all 0.2s; background:transparent; color:#94a3b8; display:flex; align-items:center; justify-content:center; gap:6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                PC / Laptop
            </button>
        </div>

        <!-- Android Pane -->
        <div id="paneAndroid" class="install-tab-pane" style="display:block; text-align:left;">
            <!-- Primary Option: Direct APK Download -->
            <div style="background:linear-gradient(135deg, rgba(16,67,159,0.35) 0%, rgba(37,99,235,0.2) 100%); border:1.5px solid #2563eb; border-radius:16px; padding:15px; margin-bottom:12px; position:relative; overflow:hidden;">
                <div style="position:absolute; top:10px; right:12px; background:#10b981; color:#fff; font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px; text-transform:uppercase; letter-spacing:0.5px;">Recommended</div>
                <div style="font-size:13.5px; font-weight:800; color:#ffffff; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                    ⚡ Direct Android App (.apk)
                </div>
                <p style="font-size:12px; color:#cbd5e1; margin:0 0 12px; line-height:1.45;">
                    100% guaranteed on all Android phones (POCO, Infinix, Tecno, Xiaomi, Samsung). Installs native app with zero Chrome menu issues.
                </p>
                <a href="/api/download_app.php" download="TDS_Attendance.apk" style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:11px 14px; background:linear-gradient(135deg, #10439f, #2563eb); color:#ffffff; text-decoration:none; border-radius:11px; font-weight:800; font-size:13px; box-shadow:0 4px 14px rgba(37,99,235,0.4); box-sizing:border-box;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download Android App (.apk)
                </a>
            </div>

            <!-- Option 2: Browser PWA / Add to Home screen -->
            <div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:14px; padding:13px; margin-bottom:12px;">
                <div style="font-size:12.5px; font-weight:700; color:#e2e8f0; margin-bottom:10px;">
                    Or Add to Home screen via Chrome:
                </div>
                <div style="font-size:12px; color:#cbd5e1; line-height:1.6;">
                    <div style="display:flex; align-items:flex-start; gap:8px; margin-bottom:8px;">
                        <span style="background:#1e293b; color:#38bdf8; min-width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; border:1px solid #334155;">1</span>
                        <span>Tap the <strong>three dots (⋮)</strong> in the top-right corner of Chrome.</span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px; margin-bottom:8px;">
                        <span style="background:#1e293b; color:#38bdf8; min-width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; border:1px solid #334155;">2</span>
                        <span><strong>Scroll DOWN</strong> past <em>'Translate'</em> (swipe up on the menu).</span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="background:#1e293b; color:#38bdf8; min-width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; border:1px solid #334155;">3</span>
                        <span>Tap <strong>'Install app'</strong> or <strong>'Add to Home screen'</strong>.</span>
                    </div>
                </div>
            </div>

            <!-- POCO & Infinix Special Troubleshooting Note -->
            <div style="background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.25); border-radius:12px; padding:11px 13px; font-size:11.5px; color:#fde68a; line-height:1.5;">
                <strong style="color:#fbbf24;">⚠️ POCO / Xiaomi / Infinix Users:</strong><br>
                If the icon doesn't appear on your screen: Long-press your phone Home screen &rarr; <strong>Settings</strong> &rarr; Turn OFF <strong>'Lock Home screen layout'</strong>. Under Phone Settings &rarr; Apps &rarr; Chrome, allow <strong>'Home screen shortcuts'</strong>.
            </div>
        </div>

        <!-- iOS Pane -->
        <div id="paneIos" class="install-tab-pane" style="display:none; text-align:left;">
            <div style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:16px; padding:16px; font-size:12.5px; color:#e2e8f0; line-height:1.65;">
                <div style="font-size:13.5px; font-weight:800; color:#ffffff; margin-bottom:12px;">
                    Install in Safari (2 quick taps):
                </div>
                <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:12px;">
                    <span style="background:#10439f; color:#fff; min-width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">1</span>
                    <span>Tap the <strong>Share</strong> button <svg style="vertical-align:middle; display:inline; margin:0 2px;" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.3"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg> at the bottom of Safari.</span>
                </div>
                <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:12px;">
                    <span style="background:#10439f; color:#fff; min-width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">2</span>
                    <span>Scroll down and tap <strong>'Add to Home Screen'</strong> (➕).</span>
                </div>
                <div style="display:flex; align-items:flex-start; gap:10px;">
                    <span style="background:#10439f; color:#fff; min-width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">3</span>
                    <span>Tap <strong>'Add'</strong> in the top-right corner.</span>
                </div>
            </div>
        </div>

        <!-- Desktop Pane -->
        <div id="paneDesktop" class="install-tab-pane" style="display:none; text-align:left;">
            <div style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:16px; padding:16px; font-size:12.5px; color:#e2e8f0; line-height:1.65;">
                <div style="font-size:13.5px; font-weight:800; color:#ffffff; margin-bottom:12px;">
                    Install on Chrome or Edge:
                </div>
                <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:12px;">
                    <span style="background:#10439f; color:#fff; min-width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">1</span>
                    <span>Look at the browser address bar at the top-right.</span>
                </div>
                <div style="display:flex; align-items:flex-start; gap:10px;">
                    <span style="background:#10439f; color:#fff; min-width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">2</span>
                    <span>Click the <strong>Install</strong> icon (computer with down arrow or ⊕) and confirm <strong>'Install'</strong>.</span>
                </div>
            </div>
        </div>

        <button type="button" onclick="closeSmartInstallModal()" style="width:100%; margin-top:16px; padding:12px; border-radius:12px; background:rgba(255,255,255,0.1); color:#ffffff; font-weight:700; border:1px solid rgba(255,255,255,0.15); cursor:pointer; font-size:13px; transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.18)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">Close</button>
    </div>
</div>