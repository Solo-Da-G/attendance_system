<?php
include(__DIR__ . "/../includes/config.php");

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

$is_admin = isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin');
$staff_id = $_SESSION['staff_id'] ?? null;
$display_name = $_SESSION['admin'] ?? 'User';
$staff_branch_name = '';
$staff_photo_ok = false;
$staff_job_title = '';
$staff_department = '';
$staff_photo = '';

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
        $photo_stmt->close();
    }
}

// TEMPORARY: Enable errors if something is failing
if ($is_admin) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

try {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Attendance System</title>
  <link rel="stylesheet" href="/asset/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    body.dashboard-page { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; overflow-x: hidden; }
    .dashboard-page .content { width: 100%; max-width: 100%; box-sizing: border-box; }

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
        width: min(280px, 78vw); margin: 0 auto 20px;
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
        width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;
    }
    .table-scroll table { width: 100%; min-width: 520px; border-collapse: collapse; }
    .dashboard-page .recent-table th {
        text-align: left; padding: clamp(10px, 2.5vw, 18px) clamp(12px, 2.5vw, 20px);
        font-size: clamp(0.75rem, 2.2vw, 0.875rem); white-space: nowrap;
    }
    .dashboard-page .recent-table td {
        padding: clamp(10px, 2.5vw, 16px) clamp(12px, 2.5vw, 20px);
        font-size: clamp(0.8rem, 2.4vw, 0.9375rem);
    }
    .staff-thumb { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; margin-right: 8px; vertical-align: middle; }

    .dashboard-page .footer { margin-top: 24px; font-size: clamp(0.75rem, 2vw, 0.875rem); text-align: center; }

    @media (max-width: 850px) {
        .dashboard-page .search-section .search-icon { left: 14px; }
    }

    @media (max-width: 600px) {
        .staff-thumb { width: 32px; height: 32px; }
        .table-scroll table { min-width: 480px; }
    }

    @media (max-width: 380px) {
        #camera-container { width: min(240px, 88vw); border-width: 4px; }
    }
  </style>
  <script src="/asset/js/idle-logout.js" defer></script>
</head>
<body class="dashboard-page">

  <?php include(__DIR__ . "/../includes/sidebar.php"); ?>

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
                <div style="font-size: 42px; font-weight: 800; margin: 4px 0; font-variant-numeric: tabular-nums; display: flex; align-items: baseline; gap: 8px; letter-spacing: -1px;">
                    <span id="liveTime">00:00:00</span>
                    <span id="liveAmPm" style="font-size: 20px; font-weight: 600; opacity: 0.9;"></span>
                </div>
            </div>
            <div style="position: relative; z-index: 2; opacity: 0.9; font-size: 48px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));">
                ⏰
            </div>
        </div>
        
        <div class="widget-card info-widget" style="background: white; padding: 24px 30px; border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 24px;">
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
    
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): 
        $del_adm = $conn->query("SELECT id FROM `admin` WHERE deleted_at IS NOT NULL")->num_rows;
        $del_stf = $conn->query("SELECT id FROM `staff` WHERE deleted_at IS NOT NULL")->num_rows;
        $total_del = $del_adm + $del_stf;
        if ($total_del > 0):
    ?>
    <div style="background:rgba(255, 255, 255, 0.7);backdrop-filter:blur(16px);border:1px solid rgba(239, 68, 68, 0.2);padding:20px;border-radius:16px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 10px 15px -3px rgba(0,0,0,0.05);">
        <div>
            <h3 style="margin:0 0 4px;color:#b91c1c;font-size:16px;display:flex;align-items:center;gap:8px;">🗑️ Recycle Bin Alert</h3>
            <p style="margin:0;color:#94a3b8;font-size:14px;">There are <strong><?php echo $total_del; ?></strong> items in the recycle bin pending permanent deletion.</p>
        </div>
        <a href="recycle_bin.php" style="background:#ef4444;color:white;padding:10px 16px;border-radius:10px;text-decoration:none;font-weight:600;font-size:14px;transition:background 0.2s;">View Bin</a>
    </div>
    <?php endif; endif; ?>
    
    <?php endif; ?>

    <?php if ($staff_id): ?>
    <div class="clocking-card" style="text-align:center;">
        <h3>📸 Face Clocking (Geofenced)</h3>
        <p style="color:var(--text-muted); margin-bottom:12px;">Sign in with your <strong>Staff ID</strong> (not admin). Center your face in the camera.</p>
        <?php if (!$staff_photo_ok): ?>
        <p style="background:#fef2f2;color:#b91c1c;padding:12px;border-radius:12px;font-size:14px;margin-bottom:16px;">
            ⚠️ Profile photo missing or broken. Admin: re-upload a clear face photo in <strong>Employees → Edit</strong>.
        </p>
        <?php endif; ?>
        <?php if ($staff_branch_name): ?>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px;">Branch on file: <strong><?php echo htmlspecialchars($staff_branch_name); ?></strong> — you can clock at <em>any</em> branch within its radius.</p>
        <?php endif; ?>

        <div id="apiResult" style="margin-bottom: 15px;"></div>

        <div id="camera-container">
            <video id="video" autoplay playsinline></video>
            <div id="scanningOverlay" class="scanning-overlay"></div>
            <canvas id="canvas" width="640" height="480"></canvas>
        </div>

        <?php
            $is_clocked_in = false;
            $stmt = $conn->prepare("SELECT id FROM `attendance` WHERE staff_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $staff_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $is_clocked_in = true;
                }
                $stmt->close();
            }
        ?>
        
        <div id="clockControls">
            <?php if (!$is_clocked_in): ?>
                <button id="clockBtn" class="clock-btn" disabled onclick="processClocking('clock_in')">Verify & Clock In</button>
            <?php else: ?>
                <button id="clockBtn" class="clock-btn out" disabled onclick="processClocking('clock_out')">Verify & Clock Out</button>
            <?php endif; ?>
        </div>
        
        <p id="faceStatus" class="status-line">🔄 Loading face recognition...</p>
        <div id="gpsCoordsBox" class="gps-coords-box" style="display:none;">
            <strong>Your phone GPS right now:</strong><br>
            <span id="liveGpsText">Waiting…</span>
        </div>
        <p id="geoStatus" class="status-line">📍 Detecting location...</p>
        <p id="locationRegisterHint" class="status-line" style="color:#0f766e;"></p>
        <button type="button" id="registerLocBtn" class="geo-register-btn" onclick="registerMyClockLocation()">
            📍 Register my clock location (use once at home/office)
        </button>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px;max-width:360px;margin-left:auto;margin-right:auto;">
            Google Maps coords often differ from phone GPS by 1–3 km. Register while standing where you clock in — this fixes “outside allowed area”.
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
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="my_attendance.php" style="padding:10px 14px;border-radius:14px;background:var(--surface-alt);border:1px solid var(--border);font-weight:700;">My Attendance</a>
                <a href="my_report.php" style="padding:10px 14px;border-radius:14px;background:var(--surface-alt);border:1px solid var(--border);font-weight:700;">My Report</a>
                <a href="my_profile.php" style="padding:10px 14px;border-radius:14px;background:linear-gradient(135deg,var(--primary),var(--primary-light));border:1px solid transparent;color:#fff;font-weight:800;">My Profile</a>
            </div>
        </div>
    </div>
    
    <div class="recent-table" style="margin-top: 30px;">
        <h3>🕒 Your Recent Attendance</h3>
        <div class="table-scroll">
        <table id="staffAttendanceTable">
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
                $stmt = $conn->prepare("SELECT clock_in, clock_out, status FROM attendance WHERE staff_id = ? ORDER BY clock_in DESC LIMIT 5");
                if ($stmt) {
                    $stmt->bind_param("s", $staff_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) {
                        $in = date("M d, Y h:i A", strtotime($r['clock_in']));
                        $out = $r['clock_out'] ? date("h:i A", strtotime($r['clock_out'])) : '—';
                        $stat = "<span class='badge badge-" . ($r['status']=='in' ? 'success' : 'warning') . "'>" . strtoupper($r['status']) . "</span>";
                        echo "<tr><td>".date("M d, Y", strtotime($r['clock_in']))."</td><td>".date("h:i A", strtotime($r['clock_in']))."</td><td>$out</td><td>$stat</td></tr>";
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
    $th = 0;
    $th_today = 0;
    $stmt_th = $conn->prepare("SELECT SUM(total_hours) as th FROM attendance WHERE staff_id = ?");
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
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-top: 24px;">
        <div class="widget-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 24px; border-radius: 20px; box-shadow: 0 10px 20px -5px rgba(16,185,129,0.3); display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden;">
            <div style="position: absolute; right: -20px; bottom: -20px; font-size: 80px; opacity: 0.1;">⏳</div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; position: relative; z-index: 2;">Total Time in Office</div>
            <div style="font-size: 26px; font-weight: 800; position: relative; z-index: 2;"><?php echo formatHours($th); ?></div>
        </div>
        <div class="widget-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 24px; border-radius: 20px; box-shadow: 0 10px 20px -5px rgba(245,158,11,0.3); display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden;">
            <div style="position: absolute; right: -20px; bottom: -20px; font-size: 80px; opacity: 0.1;">⌚</div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; position: relative; z-index: 2;">Time Tracked Today</div>
            <div style="font-size: 26px; font-weight: 800; position: relative; z-index: 2;"><?php echo formatHours($th_today); ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($is_admin): ?>
    <div class="search-section">
        <span class="search-icon">🔍</span>
        <input type="text" id="staffSearch" class="search-input" placeholder="Search staff records..." onkeyup="filterTable()">
    </div>

    <div class="recent-table">
        <div class="table-scroll">
        <table id="attendanceTable">
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
                        $status_badge = $row['clock_out'] ? 'badge-info' : 'badge-success';
                        $status_text = $row['clock_out'] ? 'Completed' : 'Working';
                        $selfie = $row['photo_in'] ?: $row['photo_out'];
                        
                        echo "<tr>";
                        echo "<td>";
                        if($row['photo']) echo "<img src='{$row['photo']}' class='staff-thumb'>";
                        echo "<strong>".htmlspecialchars($row['full_name'])."</strong></td>";
                        echo "<td><code>".htmlspecialchars($row['staff_id'])."</code></td>";
                        echo "<td>".date('M j, g:i A', strtotime($row['clock_in']))."</td>";
                        echo "<td>".($row['clock_out'] ? date('M j, g:i A', strtotime($row['clock_out'])) : '—')."</td>";
                        echo "<td>";
                        if($selfie) echo "<img src='{$selfie}' class='staff-thumb' style='border-radius:4px; cursor:pointer;' onclick='showFull(this.src)'>";
                        else echo "<span style='color:#ccc;'>No photo</span>";
                        echo "</td>";
                        echo "<td><span class='badge {$status_badge}'>{$status_text}</span></td>";
                        echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center; padding:40px; color:#94a3b8;'>No attendance records yet.</td></tr>";
                    }
                ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="footer">&copy; <?php echo date("Y"); ?> Attendance System | Solomon Mbewu</div>
  </div>

  <?php if ($staff_id): ?>
  <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.14/dist/face-api.js"></script>
  <script src="/asset/js/clock-face.js"></script>
  <?php endif; ?>

  <script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const clockBtn = document.getElementById('clockBtn');
    let currentCoords = null;
    let faceReady = false;
    let geoReady = false;
    let branchList = [];
    let personalClock = null;

    function updateClockButtonState() {
        if (!clockBtn) return;
        clockBtn.disabled = !(faceReady && geoReady);
    }

    function haversineM(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    function updateGeoStatusText(coords) {
        const el = document.getElementById('geoStatus');
        const live = document.getElementById('liveGpsText');
        const box = document.getElementById('gpsCoordsBox');
        if (!coords) return;
        if (box) box.style.display = 'block';
        if (live) {
            live.textContent = coords.latitude.toFixed(7) + ', ' + coords.longitude.toFixed(7) +
                ' (±' + Math.round(coords.accuracy || 0) + 'm)';
        }
        if (!el) return;

        let inside = false;
        let html = '✅ GPS (±' + Math.round(coords.accuracy || 0) + 'm) · ';

        if (personalClock && personalClock.lat) {
            const pd = Math.round(haversineM(coords.latitude, coords.longitude, personalClock.lat, personalClock.lng));
            const pr = personalClock.radius + Math.min(coords.accuracy || 0, 200);
            if (pd <= pr) inside = true;
            html += (pd <= pr ? 'Inside' : 'Outside') + ' your registered spot (~' + pd + 'm / ' + pr + 'm)';
        } else if (branchList.length) {
            const near = branchList.map(b => ({
                name: b.branch_name,
                d: Math.round(haversineM(coords.latitude, coords.longitude, parseFloat(b.latitude), parseFloat(b.longitude))),
                r: parseInt(b.radius_meters, 10) || 200
            })).sort((a, b) => a.d - b.d);
            const closest = near[0];
            inside = closest.d <= closest.r + Math.min(coords.accuracy || 0, 200);
            html += (inside ? 'Inside' : 'Outside') + ' "' + closest.name + '" (~' + closest.d + 'm / ' + closest.r + 'm)';
            if (!inside) html += ' — tap Register my clock location below';
        } else {
            html += 'Lat ' + coords.latitude.toFixed(5) + ', Lng ' + coords.longitude.toFixed(5);
        }
        el.innerHTML = html;
        el.style.color = inside ? 'var(--success, #059669)' : '#b45309';
    }

    async function registerMyClockLocation() {
        const hint = document.getElementById('locationRegisterHint');
        const btn = document.getElementById('registerLocBtn');
        if (btn) btn.disabled = true;
        if (hint) hint.textContent = 'Getting GPS…';
        try {
            const pos = await getFreshPosition();
            currentCoords = pos.coords;
            const fd = new FormData();
            fd.append('lat', pos.coords.latitude);
            fd.append('lng', pos.coords.longitude);
            fd.append('sync_branch', '1');
            const res = await fetch('register_clock_location.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await res.json();
            if (data.status === 'success') {
                personalClock = { lat: data.lat, lng: data.lng, radius: data.radius || 300 };
                if (hint) {
                    hint.innerHTML = '✅ ' + data.message +
                        (data.branch_updated ? ' Branch "<strong>' + data.branch_updated + '</strong>" coords updated to match your phone.' : '');
                }
                updateGeoStatusText(pos.coords);
                const profRes = await fetch('staff_profile.php', { credentials: 'same-origin' });
                const prof = await profRes.json();
                if (prof.branches) branchList = prof.branches;
            } else {
                if (hint) hint.textContent = '❌ ' + (data.message || 'Failed');
            }
        } catch (e) {
            if (hint) hint.textContent = '❌ ' + (e.message || 'GPS failed');
        }
        if (btn) btn.disabled = false;
    }

    function getFreshPosition() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('GPS not supported on this device'));
                return;
            }
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: 25000
            });
        });
    }

  <?php if ($staff_id): ?>
    (async function initStaffClocking() {
        const faceStatus = document.getElementById('faceStatus');
        try {
            const profRes = await fetch('staff_profile.php', { credentials: 'same-origin' });
            const prof = await profRes.json();
            if (prof.status !== 'success') {
                throw new Error(prof.message || 'Could not load staff profile');
            }
            branchList = prof.branches || [];
            if (prof.location_set) {
                personalClock = {
                    lat: parseFloat(prof.clock_lat),
                    lng: parseFloat(prof.clock_lng),
                    radius: prof.clock_radius || 300
                };
                const lh = document.getElementById('locationRegisterHint');
                if (lh) lh.textContent = '✅ Personal clock zone registered. You can re-register if you move.';
            } else {
                const lh = document.getElementById('locationRegisterHint');
                if (lh) lh.innerHTML = '⚠️ <strong>First time:</strong> tap Register my clock location while at home, then clock in.';
            }
            if (!prof.photo_ok) {
                throw new Error('Profile photo missing or corrupted. Re-upload in Employees.');
            }
            await ClockFace.loadModels();
            await ClockFace.loadProfilePhoto(prof.photo);
            faceReady = true;
            faceStatus.innerHTML = '✅ Face ready for ' + (prof.full_name || 'you') + ' — tap Verify when your face is centered';
        } catch (e) {
            faceStatus.innerHTML = '❌ ' + e.message;
            if (clockBtn) clockBtn.disabled = true;
        }
        updateClockButtonState();
    })();

    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia && video) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } })
        .then(stream => { video.srcObject = stream; })
        .catch(() => {
            document.getElementById('camera-container').innerHTML =
                "<p style='color:white;padding:20px;text-align:center;'>Camera denied. Allow camera in browser settings and refresh.</p>";
        });
    }

    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(
            (pos) => {
                currentCoords = pos.coords;
                geoReady = true;
                updateGeoStatusText(pos.coords);
                updateClockButtonState();
            },
            () => {
                document.getElementById('geoStatus').innerHTML = '❌ GPS denied — allow location (use phone, not desktop Wi‑Fi location)';
                geoReady = false;
                updateClockButtonState();
            },
            { enableHighAccuracy: true, maximumAge: 5000, timeout: 25000 }
        );
    }
  <?php endif; ?>

    function playBeep(freq, duration, type='sine') {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = type;
            osc.frequency.value = freq;
            gain.gain.value = 0.1;
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            setTimeout(() => { osc.stop(); ctx.close(); }, duration);
        } catch (e) { /* audio optional */ }
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
        }
    }

    async function processClocking(action) {
        if (typeof ClockFace === 'undefined') {
            alert('Face verification not loaded. Refresh the page.');
            return;
        }
        if (!ClockFace.isReady()) {
            alert('Face recognition still loading. Wait for the green face message.');
            return;
        }

        if (typeof pauseIdleLogout === 'function') pauseIdleLogout();

        playBeep(800, 200, 'square');
        document.getElementById('scanningOverlay').style.display = 'block';
        document.getElementById('camera-container').style.borderColor = '#3b82f6';

        clockBtn.disabled = true;
        const originalText = clockBtn.innerHTML;
        clockBtn.innerHTML = 'Getting GPS…';

        try {
            const pos = await getFreshPosition();
            currentCoords = pos.coords;
            geoReady = true;
            updateGeoStatusText(pos.coords);
        } catch (e) {
            showApiError('GPS failed: ' + (e.message || 'enable location and try again'));
            clockBtn.disabled = false;
            clockBtn.innerHTML = originalText;
            if (typeof resumeIdleLogout === 'function') resumeIdleLogout();
            return;
        }

        clockBtn.innerHTML = 'Verifying face…';

        let faceResult = { match: false, distance: 999, message: '' };
        try {
            faceResult = await ClockFace.verifyVideoFace(video);
        } catch (e) {
            showApiError(e.message || 'Face verification failed.');
            clockBtn.disabled = false;
            clockBtn.innerHTML = originalText;
            if (typeof resumeIdleLogout === 'function') resumeIdleLogout();
            return;
        }

        if (!faceResult.match) {
            playBeep(300, 400, 'sawtooth');
            showApiError(faceResult.message);
            clockBtn.disabled = false;
            clockBtn.innerHTML = originalText;
            if (typeof resumeIdleLogout === 'function') resumeIdleLogout();
            setTimeout(() => { document.getElementById('camera-container').style.borderColor = '#e2e8f0'; }, 2000);
            return;
        }

        const ctx = canvas.getContext('2d');
        canvas.width = 320;
        canvas.height = 240;
        ctx.drawImage(video, 0, 0, 320, 240);
        const photoData = canvas.toDataURL('image/jpeg', 0.55);

        clockBtn.innerHTML = 'Saving attendance…';

        const formData = new FormData();
        formData.append('action', action);
        formData.append('lat', currentCoords.latitude);
        formData.append('lng', currentCoords.longitude);
        formData.append('gps_accuracy', String(currentCoords.accuracy || 0));
        formData.append('photo', photoData);
        formData.append('face_verified', '1');
        formData.append('face_distance', String(faceResult.distance));
        formData.append('face_descriptor', JSON.stringify(faceResult.descriptor || []));

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 45000);

        try {
            const res = await fetch('/api/web_clock.php', { method: 'POST', body: formData, signal: controller.signal });
            clearTimeout(timeoutId);
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                throw new Error('Server returned an invalid response. Try again.');
            }

            document.getElementById('scanningOverlay').style.display = 'none';
            if (data.status === 'success') {
                playBeep(1200, 400, 'sine');
                document.getElementById('camera-container').style.borderColor = '#10b981';
                document.getElementById('apiResult').style.color = '#10b981';
                document.getElementById('apiResult').innerHTML = '✅ VERIFIED! ' + data.message;
                clockBtn.innerHTML = 'Verified';
                setTimeout(() => location.reload(), 2000);
            } else {
                playBeep(300, 400, 'sawtooth');
                let errMsg = data.message || 'Verification failed.';
                if (data.distances && data.distances.length) {
                    errMsg += '<br><small style="font-weight:400;">Distances: ' +
                        data.distances.map(d => d.name + ' ' + d.distance + 'm/' + d.radius + 'm').join(' · ') +
                        '</small>';
                }
                showApiError(errMsg);
                clockBtn.disabled = false;
                clockBtn.innerHTML = originalText;
                setTimeout(() => { document.getElementById('camera-container').style.borderColor = '#e2e8f0'; }, 2000);
            }
        } catch (err) {
            clearTimeout(timeoutId);
            const msg = err.name === 'AbortError'
                ? 'Request timed out. Check your connection and try again.'
                : (err.message || 'Network error. Please try again.');
            showApiError(msg);
            clockBtn.disabled = false;
            clockBtn.innerHTML = originalText;
        } finally {
            if (typeof resumeIdleLogout === 'function') resumeIdleLogout();
        }
    }

    function filterTable() {
        const input = document.getElementById("staffSearch");
        const filter = input.value.toUpperCase();
        const tr = document.getElementById("attendanceTable").getElementsByTagName("tr");
        for (let i = 1; i < tr.length; i++) {
            let found = false;
            const tds = tr[i].getElementsByTagName("td");
            for (let j = 0; j < tds.length; j++) {
                if (tds[j].textContent.toUpperCase().indexOf(filter) > -1) { found = true; break; }
            }
            tr[i].style.display = found ? "" : "none";
        }
    }

    function showFull(src) { window.open(src, '_blank'); }

    function updateClock() {
        const now = new Date();
        
        // Time
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
        
        // Date
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        const liveDayName = document.getElementById('liveDayName');
        const liveDateStr = document.getElementById('liveDateStr');
        
        if(liveDayName) liveDayName.textContent = days[now.getDay()];
        if(liveDateStr) liveDateStr.textContent = months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
    }
    
    setInterval(updateClock, 1000);
    updateClock();
  </script>
</body>
</html>
<?php
} catch (Throwable $t) {
    echo "<div style='padding:40px; background:#fee2e2; color:#991b1b; font-family:sans-serif;'>";
    echo "<h2>Fatal Application Error</h2>";
    echo "<p><strong>Message:</strong> " . $t->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $t->getFile() . " on line " . $t->getLine() . "</p>";
    echo "<pre>" . $t->getTraceAsString() . "</pre>";
    echo "</div>";
}
?>
