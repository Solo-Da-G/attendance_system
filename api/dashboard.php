<?php
include(__DIR__ . "/includes/config.php");

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

$today_date = date("Y-m-d");
// Auto-close any stale open attendance (previous days) so they show as "Missed clock-out" from 12:00am.
try {
    $stale = $conn->prepare("SELECT id, clock_in FROM attendance WHERE clock_out IS NULL AND DATE(clock_in) < CURDATE() ORDER BY id DESC LIMIT 50");
    if ($stale) {
        $stale->execute();
        $rs = $stale->get_result();
        while ($r = $rs->fetch_assoc()) {
            $clock_in_date = date("Y-m-d", strtotime($r['clock_in']));
            $midnight = date("Y-m-d 00:00:00", strtotime($clock_in_date . " +1 day"));
            $upd = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'missed_out', total_hours = 0 WHERE id = ? AND clock_out IS NULL");
            if ($upd) {
                $upd->bind_param("si", $midnight, $r['id']);
                $upd->execute();
                $upd->close();
            }
        }
        $stale->close();
    }
} catch (Throwable $t) { /* ignore */ }

$is_admin = isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin');
$staff_id = $_SESSION['staff_id'] ?? null;
$display_name = $_SESSION['admin'] ?? 'User';
$staff_branch_name = '';
$staff_photo_ok = false;
$staff_job_title = '';
$staff_department = '';
$staff_photo = '';
$staff_photo_error = '';
$style_version = @filemtime(dirname(__DIR__) . "/asset/css/style.css") ?: time();
$face_api_version = @filemtime(dirname(__DIR__) . "/asset/js/face-api.min.js") ?: time();
$clock_face_version = @filemtime(dirname(__DIR__) . "/asset/js/clock-face.js") ?: time();
$idle_logout_version = @filemtime(dirname(__DIR__) . "/asset/js/idle-logout.js") ?: time();
$th = 0;
$th_today = 0;
$th_yest = 0;

if (!function_exists('formatHours')) {
    function formatHours($decimal) {
        if (!$decimal) return "0 hrs 0 min";
        $h = floor($decimal);
        $m = floor(($decimal - $h) * 60);
        $s = round((($decimal - $h) * 60 - $m) * 60);
        $str = "";
        if ($h > 0) $str .= "{$h}hrs ";
        $str .= "{$m}min";
        if ($s > 0) $str .= " {$s}sec";
        return trim($str);
    }
}

if ($staff_id) {
    $photo_stmt = $conn->prepare("SELECT photo, branch, job_title, department FROM staff WHERE staff_id = ? LIMIT 1");
    if ($photo_stmt) {
        $photo_stmt->bind_param("s", $staff_id);
        $photo_stmt->execute();
        $photo_row = $photo_stmt->get_result()->fetch_assoc();
        $staff_branch_name = $photo_row['branch'] ?? '';
        $p = $photo_row['photo'] ?? '';
        $staff_photo = $p;
        $staff_job_title = $photo_row['job_title'] ?? '';
        $staff_department = $photo_row['department'] ?? '';
        $staff_photo_ok = strlen($p) > 500 && str_starts_with($p, 'data:image');
        
        if (!$staff_photo_ok && !empty($p)) {
            $staff_photo_error = "Photo exists but format is invalid (length: " . strlen($p) . "). Please re-upload.";
        } elseif (empty($p)) {
            $staff_photo_error = "No profile photo uploaded. Please ask admin to upload your photo.";
        }
        $photo_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Dashboard — Attendance System</title>
  <link rel="stylesheet" href="/asset/css/style.css?v=<?php echo $style_version; ?>">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    body.dashboard-page { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); overflow-x: hidden; }
    .dashboard-page .content { width: 100%; max-width: 100%; box-sizing: border-box; padding: 20px; }

    .dashboard-header {
        background: linear-gradient(135deg, #1e293b, #334155);
        color: white; padding: clamp(20px, 4vw, 40px); border-radius: clamp(16px, 3vw, 24px);
        margin-bottom: clamp(16px, 3vw, 30px);
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden;
    }
    .dashboard-header h2 { font-size: clamp(1.25rem, 4vw, 2rem); font-weight: 800; margin: 0 0 8px; line-height: 1.2; }
    .dashboard-header p { margin: 0; font-size: clamp(0.85rem, 2.5vw, 1rem); opacity: 0.9; }

    .clocking-card {
        background: white; padding: clamp(16px, 4vw, 30px); border-radius: clamp(16px, 3vw, 24px);
        margin-bottom: clamp(16px, 3vw, 30px);
        border: 1px solid var(--border); box-shadow: var(--shadow);
    }
    .clocking-card h3 { font-size: clamp(1rem, 3vw, 1.25rem); margin-bottom: 8px; }

    #camera-container {
        width: min(280px, 70vw); margin: 0 auto 20px;
        border-radius: 50%; overflow: hidden; background: #000;
        aspect-ratio: 1 / 1; position: relative; border: 6px solid #e2e8f0;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        transition: border-color 0.3s ease;
    }
    #video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
    #canvas { display: none; }

    .scanning-overlay {
        position: absolute; inset: 0; border-radius: 50%;
        box-shadow: inset 0 0 0 10px rgba(59, 130, 246, 0.5);
        animation: pulse 1.5s infinite; display: none; z-index: 10;
    }
    @keyframes pulse { 0% { transform: scale(0.95); opacity: 0.5; } 50% { transform: scale(1); opacity: 1; } 100% { transform: scale(0.95); opacity: 0.5; } }

    #clockControls { display: flex; justify-content: center; width: 100%; }

    .clock-btn {
        background: var(--primary); color: white; border: none;
        padding: clamp(14px, 3vw, 18px) clamp(24px, 6vw, 50px);
        font-size: clamp(0.95rem, 2.8vw, 1.125rem); font-weight: 700;
        border-radius: 16px; cursor: pointer; transition: all 0.3s var(--ease);
        box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        width: 100%; max-width: 360px;
    }
    .clock-btn.out { background: #ef4444; box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3); }
    .clock-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; box-shadow: none; }

    .geo-register-btn {
        background: #0f766e; color: white; border: none; padding: 12px 20px;
        border-radius: 12px; font-weight: 600; font-size: 14px; cursor: pointer;
        margin-top: 10px; width: 100%; max-width: 360px;
    }
    .geo-register-btn:hover { background: #0d9488; }
    .gps-coords-box {
        background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px;
        padding: 12px; margin: 12px 0; font-size: 13px; text-align: left;
        color: #0c4a6e; word-break: break-all;
    }

    .status-line { margin-top: 8px; color: var(--text-muted); font-size: clamp(0.8rem, 2.5vw, 0.875rem); word-break: break-word; }
    #apiResult { margin-top: 15px; font-weight: 600; font-size: clamp(0.95rem, 2.8vw, 1.125rem); word-break: break-word; }
    .error-message { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 12px; margin: 10px 0; font-size: 14px; }

    .search-section { position: relative; width: 100%; }
    .search-section .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); opacity: 0.4; pointer-events: none; }
    .search-input {
        width: 100%; padding: 14px 20px 14px 44px; box-sizing: border-box;
        background: white; border: 1px solid var(--border);
        border-radius: 16px; font-size: 16px; margin-bottom: 24px;
        box-shadow: var(--shadow-sm);
    }

    .recent-table {
        background: white; border-radius: clamp(16px, 3vw, 24px); padding: clamp(8px, 2vw, 10px);
        border: 1px solid var(--border); box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    .recent-table h3 { margin: clamp(12px, 3vw, 20px); font-size: clamp(1rem, 3vw, 1.125rem); }

    .table-scroll {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table-scroll table { 
        width: 100%; 
        min-width: 500px; 
        border-collapse: collapse; 
    }
    
    /* Mobile responsive table */
    @media (max-width: 768px) {
        .table-scroll table {
            min-width: 100%;
        }
        .table-scroll th, 
        .table-scroll td {
            padding: 10px 8px;
            font-size: 12px;
        }
        .dashboard-page .content {
            padding: 12px;
        }
    }
    
    .dashboard-page .recent-table th {
        text-align: left; padding: clamp(10px, 2.5vw, 18px) clamp(12px, 2.5vw, 20px);
        font-size: clamp(0.75rem, 2.2vw, 0.875rem);
        white-space: nowrap;
    }
    .dashboard-page .recent-table td {
        padding: clamp(10px, 2.5vw, 16px) clamp(12px, 2.5vw, 20px);
        font-size: clamp(0.8rem, 2.4vw, 0.9375rem);
    }
    .staff-thumb { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; margin-right: 8px; vertical-align: middle; }

    .dashboard-page .footer { margin-top: 24px; font-size: clamp(0.75rem, 2vw, 0.875rem); text-align: center; }

    @media (max-width: 600px) {
        .staff-thumb { width: 32px; height: 32px; }
    }

    @media (max-width: 480px) {
        #camera-container { width: min(240px, 80vw); border-width: 4px; }
        .clock-btn { padding: 12px 20px; font-size: 14px; }
        .dashboard-widgets { gap: 12px; }
        .dashboard-links-container {
            flex-direction: column !important;
            gap: 8px !important;
        }
    }

    /* Floating top notification banner */
    .top-notification {
        position: fixed;
        top: -120px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 99999;
        padding: 16px 24px;
        border-radius: 16px;
        font-weight: 700;
        font-size: 15px;
        box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        transition: top 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
        max-width: 90%;
        width: 480px;
        box-sizing: border-box;
        opacity: 0;
        pointer-events: none;
    }
    .top-notification.show {
        top: 24px;
        opacity: 1;
        pointer-events: auto;
    }
    .top-notification.success {
        background: #f0fdf4;
        color: #166534;
        border: 2px solid #22c55e;
        box-shadow: 0 10px 25px rgba(34, 197, 94, 0.2);
    }
    .top-notification.error {
        background: #fef2f2;
        color: #991b1b;
        border: 2px solid #ef4444;
        box-shadow: 0 10px 25px rgba(239, 68, 68, 0.2);
    }
  </style>
</head>
<body class="app-page dashboard-page">
  <div id="topNotification" class="top-notification"></div>

  <?php include(__DIR__ . "/includes/sidebar.php"); ?>

  <div class="content">
<?php
    $hour = date('H');
    $greeting = "Good evening";
    if ($hour < 12) {
        $greeting = "Good morning";
    } elseif ($hour < 18) {
        $greeting = "Good afternoon";
    }
?>
    <div class="dashboard-header">
        <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($display_name); ?> 👋</h2>
        <p>Facial verification active · Auto sign-out after 1 minute of inactivity</p>
    </div>

    <!-- Live Time & Date Widgets -->
    <div class="dashboard-widgets" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 30px;">
        <div class="widget-card time-widget" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white; padding: 24px 30px; border-radius: 24px; box-shadow: 0 15px 30px -5px rgba(79, 70, 229, 0.4); position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between;">
            <div style="position: absolute; top: -20px; right: -20px; width: 120px; height: 120px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            <div style="position: absolute; bottom: -40px; left: 10px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            
            <div style="position: relative; z-index: 2;">
                <div style="font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.85;" id="liveDateStr">Loading...</div>
                <div style="font-size: clamp(32px, 8vw, 42px); font-weight: 800; margin: 4px 0; font-variant-numeric: tabular-nums; display: flex; align-items: baseline; gap: 8px; letter-spacing: -1px;">
                    <span id="liveTime">00:00:00</span>
                    <span id="liveAmPm" style="font-size: 20px; font-weight: 600; opacity: 0.9;"></span>
                </div>
            </div>
            <div style="position: relative; z-index: 2; opacity: 0.9; font-size: 48px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));">
                ⏰
            </div>
        </div>
        
        <div class="widget-card info-widget" style="background: white; padding: 24px 30px; border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
            <div style="width: 64px; height: 64px; border-radius: 18px; background: var(--info-bg); color: var(--info); display: flex; align-items: center; justify-content: center; font-size: 32px; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5);">
                📅
            </div>
            <div>
                <div style="font-size: 13px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Today is</div>
                <div style="font-size: 24px; font-weight: 800; color: var(--text);" id="liveDayName">Loading...</div>
            </div>
        </div>
    </div>

    <?php if ($is_admin && !$staff_id): ?>
    <div style="background:#fff7ed;border:1px solid #fdba74;color:#9a3412;padding:16px 20px;border-radius:16px;margin-bottom:24px;font-size:15px;line-height:1.5;">
        <strong>Admin account:</strong> Face clock-in only works for <strong>staff</strong> logins.
        Log out and sign in with your <strong>Staff ID</strong> and password (not your admin username).
    </div>
    <?php elseif ($staff_id): ?>
    
    <!-- Display photo error if any -->
    <?php if ($staff_photo_error): ?>
    <div class="error-message">
        <strong>⚠️ Face Verification Issue:</strong> <?php echo htmlspecialchars($staff_photo_error); ?>
    </div>
    <?php endif; ?>
    
    <div class="clocking-card">
        <h3 style="text-align:center;">📸 Face Verification & Clock In/Out</h3>
        <p style="text-align:center;color:var(--text-muted);margin-bottom:20px;">Look at the camera and click the button below</p>

        <div id="camera-container">
            <video id="video" autoplay playsinline></video>
            <div id="scanningOverlay" class="scanning-overlay"></div>
            <canvas id="canvas" width="640" height="480"></canvas>
        </div>
        <div id="apiResult" style="text-align:center;"></div>

        <?php
            // Auto-close missed clock-out from previous day(s)
            $missed_stmt = $conn->prepare("SELECT id, clock_in FROM `attendance` WHERE staff_id = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
            if ($missed_stmt) {
                $missed_stmt->bind_param("s", $staff_id);
                $missed_stmt->execute();
                $missed_res = $missed_stmt->get_result();
                if ($missed_res && $missed_res->num_rows > 0) {
                    $m = $missed_res->fetch_assoc();
                    $clock_in_date = date("Y-m-d", strtotime($m['clock_in']));
                    $today_date = date("Y-m-d");
                    if ($clock_in_date < $today_date) {
                        $midnight = date("Y-m-d 00:00:00", strtotime($clock_in_date . " +1 day"));
                        $upd_missed = $conn->prepare("UPDATE `attendance` SET clock_out = ?, status = 'missed_out', total_hours = 0 WHERE id = ? AND clock_out IS NULL");
                        if ($upd_missed) {
                            $upd_missed->bind_param("si", $midnight, $m['id']);
                            $upd_missed->execute();
                            $upd_missed->close();
                        }
                    }
                }
                $missed_stmt->close();
            }

            $has_clocked_in_today = false;
            $has_clocked_out_today = false;
            $stmt = $conn->prepare("SELECT id, clock_out FROM `attendance` WHERE staff_id = ? AND DATE(clock_in) = CURDATE() ORDER BY id DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $staff_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $has_clocked_in_today = true;
                    if ($row['clock_out'] !== null) {
                        $has_clocked_out_today = true;
                    }
                }
                $stmt->close();
            }
        ?>
        
        <div id="clockControls">
            <?php if ($has_clocked_out_today): ?>
                <button id="clockBtn" class="clock-btn" disabled data-prevent-enable="true" style="background:#64748b;box-shadow:none;">Already Clocked Out Today</button>
            <?php elseif (!$has_clocked_in_today): ?>
                <button id="clockBtn" class="clock-btn" disabled onclick="processClocking('clock_in')">Verify & Clock In</button>
            <?php else: ?>
                <button id="clockBtn" class="clock-btn out" disabled onclick="processClocking('clock_out')">Verify & Clock Out</button>
            <?php endif; ?>
        </div>
        
        <p id="faceStatus" class="status-line">🔄 Loading face recognition...</p>
        <div id="gpsCoordsBox" class="gps-coords-box" style="display:none;">
            <strong>Your GPS location:</strong><br>
            <span id="liveGpsText">Waiting…</span>
        </div>
        <p id="geoStatus" class="status-line">📍 Detecting location...</p>
        <button type="button" id="registerLocBtn" class="geo-register-btn" onclick="registerMyClockLocation()">
            📍 Register my clock location
        </button>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px;text-align:center;">
            Use this to set your exact clock-in location if GPS is inaccurate
        </p>
    </div>

    <div class="clocking-card">
        <div style="display:flex;align-items:center;gap:14px;justify-content:space-between;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:14px;">
                <?php if ($staff_photo_ok): ?>
                    <img src="<?php echo htmlspecialchars($staff_photo); ?>" alt="Profile" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid rgba(79,70,229,.35);">
                <?php else: ?>
                    <div style="width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#e2e8f0;font-weight:800;">?</div>
                <?php endif; ?>
                <div>
                    <div style="font-weight:800;font-size:16px;"><?php echo htmlspecialchars($display_name); ?></div>
                    <div style="color:var(--text-muted);font-weight:600;font-size:13px;">
                        <?php echo htmlspecialchars($staff_id); ?>
                        <?php if ($staff_job_title): ?> · <?php echo htmlspecialchars($staff_job_title); ?><?php endif; ?>
                        <?php if ($staff_department): ?> · <?php echo htmlspecialchars($staff_department); ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="dashboard-links-container" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;">
                <a href="my_attendance.php" style="padding:10px 14px;border-radius:14px;background:var(--surface-alt);border:1px solid var(--border);font-weight:700;text-decoration:none;flex:1;min-width:120px;text-align:center;">My Attendance</a>
                <a href="my_report.php" style="padding:10px 14px;border-radius:14px;background:var(--surface-alt);border:1px solid var(--border);font-weight:700;text-decoration:none;flex:1;min-width:120px;text-align:center;">My Report</a>
                <a href="my_profile.php" style="padding:10px 14px;border-radius:14px;background:linear-gradient(135deg,var(--primary),var(--primary-light));border:1px solid transparent;color:#fff;font-weight:800;text-decoration:none;flex:1;min-width:120px;text-align:center;">My Profile</a>
            </div>
        </div>
    </div>
    
    <div class="recent-table" style="margin-top: 30px;">
        <h3>🕒 Your Recent Attendance</h3>
        <div class="table-scroll">
        <table id="staffAttendanceTable" class="responsive-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->prepare("SELECT clock_in, clock_out, status FROM attendance WHERE staff_id = ? ORDER BY clock_in DESC LIMIT 10");
                if ($stmt) {
                    $stmt->bind_param("s", $staff_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) {
                        $badgeClass = 'badge-warning';
                        $label = strtoupper((string)($r['status'] ?? ''));
                        if ($r['status'] === 'in') { $badgeClass = 'badge-success'; $label = 'IN'; }
                        elseif ($r['status'] === 'out') { $badgeClass = 'badge-info'; $label = 'OUT'; }
                        elseif ($r['status'] === 'missed_out') { $badgeClass = 'badge-danger'; $label = 'missed(clockout)'; }
                        $stat = "<span class='badge {$badgeClass}'>{$label}</span>";
                        $out = $r['clock_out'] ? date("h:i A", strtotime($r['clock_out'])) : '—';
                        echo "<tr>";
                        echo "<td data-label='Date'>".date("M d, Y", strtotime($r['clock_in']))."</td>";
                        echo "<td data-label='Clock In'>".date("h:i A", strtotime($r['clock_in']))."</td>";
                        echo "<td data-label='Clock Out'>".$out."</td>";
                        echo "<td data-label='Status'>".$stat."</td>";
                        echo "</tr>";
                    }
                    if ($res->num_rows === 0) echo "<tr><td colspan='4' style='text-align:center;'>No records found.</td></tr>";
                    $stmt->close();
                }
                ?>
            </tbody>
        </table>
        </div>
    </div>
    
    <?php
    $stmt_th = $conn->prepare("SELECT SUM(total_hours) as th FROM attendance WHERE staff_id = ? AND YEARWEEK(clock_in, 1) = YEARWEEK(CURDATE(), 1) AND WEEKDAY(clock_in) <= 4");
    if ($stmt_th) {
        $stmt_th->bind_param("s", $staff_id);
        $stmt_th->execute();
        $th = $stmt_th->get_result()->fetch_assoc()['th'] ?? 0;
        $stmt_th->close();
    }
    
    $stmt_today = $conn->prepare("SELECT SUM(total_hours) as th FROM attendance WHERE staff_id = ? AND DATE(clock_in) = CURDATE()");
    if ($stmt_today) {
        $stmt_today->bind_param("s", $staff_id);
        $stmt_today->execute();
        $th_today = $stmt_today->get_result()->fetch_assoc()['th'] ?? 0;
        $stmt_today->close();
    }
    
    $stmt_yest = $conn->prepare("SELECT SUM(total_hours) as th FROM attendance WHERE staff_id = ? AND DATE(clock_in) = CURDATE() - INTERVAL 1 DAY");
    if ($stmt_yest) {
        $stmt_yest->bind_param("s", $staff_id);
        $stmt_yest->execute();
        $th_yest = $stmt_yest->get_result()->fetch_assoc()['th'] ?? 0;
        $stmt_yest->close();
    }
    
    $stmt_days = $conn->prepare("SELECT COUNT(DISTINCT DATE(clock_in)) as days FROM attendance WHERE staff_id = ? AND MONTH(clock_in) = MONTH(CURDATE()) AND YEAR(clock_in) = YEAR(CURDATE())");
    $days_present = 0;
    if ($stmt_days) {
        $stmt_days->bind_param("s", $staff_id);
        $stmt_days->execute();
        $days_present = $stmt_days->get_result()->fetch_assoc()['days'] ?? 0;
        $stmt_days->close();
    }
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-top: 24px;">
        <div class="widget-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 24px; border-radius: 20px; box-shadow: 0 10px 20px -5px rgba(16,185,129,0.3);">
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px;">Total Time This Week</div>
            <div style="font-size: 26px; font-weight: 800;"><?php echo formatHours($th); ?></div>
        </div>
        <div class="widget-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 24px; border-radius: 20px; box-shadow: 0 10px 20px -5px rgba(245,158,11,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9;" id="timeTrackedLabel">Today</div>
                <button onclick="toggleDayTracked()" style="background: rgba(255,255,255,0.35); border: 1px solid rgba(255,255,255,0.55); color: white; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                    <svg id="timeTrackedIcon" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>
                </button>
            </div>
            <div style="font-size: 26px; font-weight: 800;" id="timeTrackedValue"><?php echo formatHours($th_today); ?></div>
        </div>
        <div class="widget-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: white; padding: 24px; border-radius: 20px; box-shadow: 0 10px 20px -5px rgba(139,92,246,0.3);">
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px;">Days Present (This Month)</div>
            <div style="font-size: 26px; font-weight: 800;"><?php echo $days_present; ?></div>
        </div>
    </div>

    <?php elseif ($is_admin): ?>
    <?php
    // Admin Summary Stats
    $total_staff = 0;
    $present_today = 0;
    $absent_today = 0;

    $res_staff = $conn->query("SELECT COUNT(*) as c FROM staff");
    if ($res_staff) {
        $total_staff = $res_staff->fetch_assoc()['c'];
    }
    
    $res_present = $conn->query("SELECT COUNT(DISTINCT staff_id) as c FROM attendance WHERE DATE(clock_in) = CURDATE()");
    if ($res_present) {
        $present_today = $res_present->fetch_assoc()['c'];
    }
    
    $absent_today = max(0, $total_staff - $present_today);
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
        <div class="widget-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 20px; border-radius: 20px; box-shadow: 0 10px 20px -5px rgba(59,130,246,0.3);">
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px;">Total Staff</div>
            <div style="font-size: 26px; font-weight: 800;"><?php echo $total_staff; ?></div>
        </div>
        <div class="widget-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px; border-radius: 20px; box-shadow: 0 10px 20px -5px rgba(16,185,129,0.3);">
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px;">Present Today</div>
            <div style="font-size: 26px; font-weight: 800;"><?php echo $present_today; ?></div>
        </div>
        <div class="widget-card" style="background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); color: white; padding: 20px; border-radius: 20px; box-shadow: 0 10px 20px -5px rgba(244,63,94,0.3);">
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px;">Absent Today</div>
            <div style="font-size: 26px; font-weight: 800;"><?php echo $absent_today; ?></div>
        </div>
    </div>
    
    <div style="margin-bottom: 24px; display: flex; justify-content: flex-end; gap: 12px;">
        <a href="backup.php?action=download" target="_blank" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 14px; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3); display: inline-flex; align-items: center; gap: 8px; transition: transform 0.2s;">
            <span>💾</span> Download Backup
        </a>
        <a href="backup.php?action=email" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: white; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 14px; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3); display: inline-flex; align-items: center; gap: 8px; transition: transform 0.2s;" onclick="return confirm('Send the database backup to your email now?');">
            <span>✉️</span> Email Backup Now
        </a>
    </div>

    <!-- Hidden iframe to trigger auto backups seamlessly in the background if needed -->
    <iframe src="backup.php?action=auto" style="display:none;" title="Auto Backup Trigger"></iframe>

    <div class="search-section">
        <span class="search-icon">🔍</span>
        <input type="text" id="staffSearch" class="search-input" placeholder="Search staff records..." onkeyup="filterTable()">
    </div>

    <div class="recent-table">
        <div class="table-scroll">
        <table id="attendanceTable" class="responsive-table">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Staff ID</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Selfie</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $res = $conn->query("SELECT a.*, s.full_name, s.photo FROM attendance a JOIN staff s ON a.staff_id = s.staff_id ORDER BY a.id DESC LIMIT 50");
                    if ($res && !is_bool($res) && $res->num_rows > 0) {
                        while($row = $res->fetch_assoc()){
                        $status_badge = 'badge-success';
                        $status_text = 'Working';
                        if (($row['status'] ?? '') === 'missed_out') {
                            $status_badge = 'badge-danger';
                            $status_text = 'missed(clockout)';
                        } elseif (!empty($row['clock_out'])) {
                            $status_badge = 'badge-info';
                            $status_text = 'Completed';
                        }
                        $selfie = $row['photo_in'] ?: $row['photo_out'];
                        
                        echo "<tr>";
                        echo "<td data-label='Staff Name'>";
                        if($row['photo']) echo "<img src='{$row['photo']}' class='staff-thumb'>";
                        echo "<strong>".htmlspecialchars($row['full_name'])."</strong></td>";
                        echo "<td data-label='Staff ID'><code>".htmlspecialchars($row['staff_id'])."</code></td>";
                        echo "<td data-label='Clock In'>".date('M j, g:i A', strtotime($row['clock_in']))."</td>";
                        $clockOutLabel = '—';
                        if (!empty($row['clock_out'])) {
                            $clockOutLabel = date('M j, g:i A', strtotime($row['clock_out']));
                            if (($row['status'] ?? '') === 'missed_out') $clockOutLabel .= " <small>(missed clockout)</small>";
                        }
                        echo "<td data-label='Clock Out'>".$clockOutLabel."</td>";
                        echo "<td data-label='Selfie'>";
                        if($selfie){
                            echo "<img src='{$selfie}' class='photo' style='border-radius:12px;width:45px;height:45px;object-fit:cover;' onclick='showFull(\"{$selfie}\")'>";
                        } else {
                            echo "—";
                        }
                        echo "</td>";
                        echo "<td data-label='Status'><span class='badge {$status_badge}'>{$status_text}</span></td>";
                        echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center;'>No attendance records found</td></tr>";
                    }
                ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="footer">
        &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Mbewu
    </div>
  </div>

<?php if ($staff_id): ?>
<script src="/asset/js/face-api.min.js?v=<?php echo $face_api_version; ?>"></script>
<script src="/asset/js/clock-face.js?v=<?php echo $clock_face_version; ?>"></script>
<?php endif; ?>
<script>
    let video = null;
    let canvas = null;
    let clockBtn = null;
    let currentCoords = null;
    let locationWarmupPromise = null;

    let notificationTimeout = null;
    function showTopNotification(msg, isSuccess = false) {
        const notification = document.getElementById('topNotification');
        if (!notification) return;

        if (notificationTimeout) {
            clearTimeout(notificationTimeout);
        }
        notification.classList.remove('show', 'success', 'error');

        notification.classList.add(isSuccess ? 'success' : 'error');
        notification.innerHTML = (isSuccess ? '<span>✅</span> ' : '<span>⚠️</span> ') + msg;
        
        void notification.offsetWidth; // Force reflow
        notification.classList.add('show');

        notificationTimeout = setTimeout(() => {
            notification.classList.remove('show');
        }, 5000);
    }

    function showApiError(msg) {
        const overlay = document.getElementById('scanningOverlay');
        const cam = document.getElementById('camera-container');
        const result = document.getElementById('apiResult');
        if (overlay) overlay.style.display = 'none';
        if (cam) cam.style.borderColor = '#ef4444';
        if (result) {
            result.style.color = '#ef4444';
            result.innerHTML = '❌ ' + msg;
            setTimeout(() => {
                if (result && result.textContent.startsWith('❌')) result.innerHTML = '';
            }, 5000);
        }
        showTopNotification(msg, false);
    }

    function showApiSuccess(msg) {
        const result = document.getElementById('apiResult');
        if (result) {
            result.style.color = '#10b981';
            result.innerHTML = '✅ ' + msg;
        }
        showTopNotification(msg, true);
    }

    function getPosition(options) {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation not supported on this device'));
                return;
            }
            navigator.geolocation.getCurrentPosition(resolve, reject, options);
        });
    }

    function warmupLocation() {
        if (!navigator.geolocation || locationWarmupPromise) return locationWarmupPromise;
        locationWarmupPromise = getPosition({
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 120000
        }).then((pos) => {
            currentCoords = pos.coords;
            updateGeoStatusText(pos.coords);
            return pos;
        }).catch(() => null);
        return locationWarmupPromise;
    }

    async function getBestPosition() {
        try {
            const quick = await getPosition({
                enableHighAccuracy: true,
                timeout: 6000,
                maximumAge: 120000
            });
            currentCoords = quick.coords;
            updateGeoStatusText(quick.coords);
            return quick;
        } catch (_) {
            const fresh = await getPosition({
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0
            });
            currentCoords = fresh.coords;
            updateGeoStatusText(fresh.coords);
            return fresh;
        }
    }

    function updateGeoStatusText(coords) {
        const geoBox = document.getElementById('gpsCoordsBox');
        const geoText = document.getElementById('liveGpsText');
        if (geoBox && geoText && coords) {
            geoBox.style.display = 'block';
            geoText.innerHTML = `Lat: ${coords.latitude.toFixed(6)}, Lng: ${coords.longitude.toFixed(6)}<br>Accuracy: ±${Math.round(coords.accuracy || 0)}m`;
        }
        const geoStatus = document.getElementById('geoStatus');
        if (geoStatus) {
            geoStatus.innerHTML = coords ? '📍 Location detected ✓' : '📍 Waiting for location permission...';
            geoStatus.style.color = coords ? '#10b981' : '';
        }
    }

    async function prepareFaceRecognition() {
        const statusEl = document.getElementById('faceStatus');
        const cameraContainer = document.getElementById('camera-container');
        if (!statusEl || typeof ClockFace === 'undefined') {
            throw new Error('Face verification library not available.');
        }

        statusEl.innerHTML = '🔄 Loading face recognition...';

        const profileResp = await fetch('/api/staff_profile.php', {
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const profileData = await profileResp.json();

        if (profileData.status !== 'success' || !profileData.photo) {
            throw new Error(profileData.message || 'Your profile photo is missing. Please contact admin.');
        }

        statusEl.innerHTML = '🔄 Matching with your saved profile photo...';
        await ClockFace.loadProfilePhoto(profileData.photo);
        await ClockFace.startLiveDetection(video, cameraContainer);

        statusEl.innerHTML = '✅ Face recognition ready. Look at the camera.';
        return true;
    }

    async function registerMyClockLocation() {
        if (!navigator.geolocation) {
            alert('GPS not supported on this device');
            return;
        }

        try {
            const pos = await getBestPosition();
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;

            const formData = new FormData();
            formData.append('lat', lat);
            formData.append('lng', lng);
            formData.append('sync_branch', '1');

            const resp = await fetch('/api/register_clock_location.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const data = await resp.json();

            if (data.status === 'success') {
                alert('✅ ' + data.message);
                if (data.branch_updated) {
                    alert(`📍 Branch "${data.branch_updated}" GPS updated to your current location!`);
                }
                location.reload();
            } else {
                alert('❌ ' + (data.message || 'Registration failed'));
            }
        } catch (err) {
            alert('GPS error: ' + err.message);
        }
    }

    async function processClocking(action) {
        if (typeof ClockFace === 'undefined') {
            showApiError('Face verification not loaded. Refresh the page.');
            return;
        }
        if (!ClockFace.isReady()) {
            showApiError('Face recognition is still loading. Please wait a moment.');
            return;
        }

        const overlay = document.getElementById('scanningOverlay');
        const cameraContainer = document.getElementById('camera-container');
        const btn = document.getElementById('clockBtn');
        if (!btn || !video || !canvas) return;

        overlay.style.display = 'block';
        cameraContainer.style.borderColor = '#3b82f6';
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Checking face & GPS…';

        let posResult;
        let faceResult;

        const [posSettled, faceSettled] = await Promise.allSettled([
            getBestPosition(),
            ClockFace.verifyVideoFace(video)
        ]);

        if (posSettled.status === 'fulfilled') {
            posResult = posSettled.value;
        } else {
            showApiError('GPS failed: ' + (posSettled.reason?.message || 'Please enable location access'));
            btn.disabled = false;
            btn.innerHTML = originalText;
            overlay.style.display = 'none';
            return;
        }

        if (faceSettled.status === 'fulfilled') {
            faceResult = faceSettled.value;
        } else {
            showApiError(faceSettled.reason?.message || 'Face verification failed.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            overlay.style.display = 'none';
            return;
        }

        if (!faceResult.match) {
            showApiError(faceResult.message || 'Face does not match your profile photo.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            overlay.style.display = 'none';
            setTimeout(() => { cameraContainer.style.borderColor = '#e2e8f0'; }, 2000);
            return;
        }

        const ctx = canvas.getContext('2d');
        canvas.width = 320;
        canvas.height = 240;
        ctx.drawImage(video, 0, 0, 320, 240);
        const photoData = canvas.toDataURL('image/jpeg', 0.6);

        btn.innerHTML = 'Saving attendance…';

        const formData = new FormData();
        formData.append('action', action);
        formData.append('lat', posResult.coords.latitude);
        formData.append('lng', posResult.coords.longitude);
        formData.append('gps_accuracy', String(posResult.coords.accuracy || 0));
        formData.append('photo', photoData);
        formData.append('face_verified', '1');
        formData.append('face_distance', String(faceResult.distance));

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 30000);

        try {
            const res = await fetch('/api/web_clock.php', {
                method: 'POST',
                body: formData,
                signal: controller.signal,
                credentials: 'same-origin'
            });
            clearTimeout(timeoutId);

            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                throw new Error('Server returned an invalid response. Try again.');
            }

            overlay.style.display = 'none';
            if (data.status === 'success') {
                cameraContainer.style.borderColor = '#10b981';
                showApiSuccess(data.message);
                btn.innerHTML = 'Verified!';
                setTimeout(() => location.reload(), 1200);
            } else {
                let errMsg = data.message || 'Verification failed.';
                if (data.debug && data.debug.dist) {
                    errMsg += ' Distance: ' + data.debug.dist + 'm';
                }
                showApiError(errMsg);
                btn.disabled = false;
                btn.innerHTML = originalText;
                setTimeout(() => { cameraContainer.style.borderColor = '#e2e8f0'; }, 2000);
            }
        } catch (err) {
            clearTimeout(timeoutId);
            const msg = err.name === 'AbortError'
                ? 'Request timed out. Check your connection.'
                : (err.message || 'Network error. Please try again.');
            showApiError(msg);
            btn.disabled = false;
            btn.innerHTML = originalText;
            overlay.style.display = 'none';
        }
    }

    function filterTable() {
        const input = document.getElementById("staffSearch");
        if (!input) return;
        const filter = input.value.toUpperCase();
        const table = document.getElementById("attendanceTable");
        if (!table) return;
        const rows = table.getElementsByTagName("tr");
        for (let i = 1; i < rows.length; i++) {
            let found = false;
            const tds = rows[i].getElementsByTagName("td");
            for (let j = 0; j < tds.length; j++) {
                if (tds[j] && tds[j].textContent.toUpperCase().indexOf(filter) > -1) { found = true; break; }
            }
            rows[i].style.display = found ? "" : "none";
        }
    }
    
    function showFull(src) { window.open(src, '_blank'); }
    
    function updateClock() {
        const now = new Date();
        
        let h = now.getHours();
        let m = now.getMinutes();
        let s = now.getSeconds();
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        h = h ? h : 12;
        
        m = m < 10 ? '0' + m : m;
        s = s < 10 ? '0' + s : s;
        
        const liveTime = document.getElementById('liveTime');
        const liveAmPm = document.getElementById('liveAmPm');
        if(liveTime) liveTime.textContent = h + ':' + m + ':' + s;
        if(liveAmPm) liveAmPm.textContent = ampm;
        
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        const liveDayName = document.getElementById('liveDayName');
        const liveDateStr = document.getElementById('liveDateStr');
        
        if(liveDayName) liveDayName.textContent = days[now.getDay()];
        if(liveDateStr) liveDateStr.textContent = months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
    }
    
    async function init() {
        video = document.getElementById('video');
        canvas = document.getElementById('canvas');
        clockBtn = document.getElementById('clockBtn');
        const faceStatus = document.getElementById('faceStatus');

        if (!video) return;

        try {
            if (faceStatus) faceStatus.innerHTML = '🔄 Starting camera...';

            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                },
                audio: false
            });
            video.srcObject = stream;
            await video.play();

            warmupLocation();
            const success = await prepareFaceRecognition();
            if (success && clockBtn && !clockBtn.hasAttribute('data-prevent-enable')) {
                clockBtn.disabled = false;
            }
        } catch (err) {
            console.error('Camera error:', err);
            if (faceStatus) {
                faceStatus.innerHTML = err && err.message
                    ? '❌ ' + err.message
                    : '❌ Camera access denied. Please allow camera access.';
            }
        }
    }
    
    const timeToday = "<?php echo formatHours($th_today); ?>";
    const timeYest = "<?php echo formatHours($th_yest); ?>";
    let showingToday = true;
    
    function toggleDayTracked() {
        const label = document.getElementById('timeTrackedLabel');
        const val = document.getElementById('timeTrackedValue');
        const icon = document.getElementById('timeTrackedIcon');
        
        if (showingToday) {
            label.textContent = "Yesterday";
            val.textContent = timeYest;
            if(icon) icon.innerHTML = '<path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>';
            showingToday = false;
        } else {
            label.textContent = "Today";
            val.textContent = timeToday;
            if(icon) icon.innerHTML = '<path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>';
            showingToday = true;
        }
    }
    
    setInterval(updateClock, 1000);
    updateClock();
    <?php if ($staff_id): ?>
    init();
    <?php endif; ?>
</script>
<script src="/asset/js/idle-logout.js?v=<?php echo $idle_logout_version; ?>" defer></script>
</body>
</html>



