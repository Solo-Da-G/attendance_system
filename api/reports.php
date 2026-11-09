<?php
include(__DIR__ . "/includes/config.php");

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$today = date('Y-m-d');
$stat_total = 0;
$stat_today = 0;
$stat_hours = 0.0;

function formatHours($decimal) {
    if (!$decimal || $decimal <= 0) return "0 hrs 0 min";
    $h = floor($decimal);
    $m = floor(($decimal - $h) * 60);
    $s = round((($decimal - $h) * 60 - $m) * 60);
    $str = "";
    if ($h > 0) $str .= "{$h}hrs ";
    $str .= "{$m}min";
    if ($s > 0) $str .= " {$s}sec";
    return trim($str);
}

$r = $conn->query("SELECT COUNT(*) AS c, SUM(COALESCE(total_hours,0)) AS h FROM attendance");
if ($r && !is_bool($r)) {
  $row = $r->fetch_assoc();
  $stat_total = (int)($row['c'] ?? 0);
  $stat_hours = (float)($row['h'] ?? 0);
}

$stmtStat = $conn->prepare("SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in) = ?");
if ($stmtStat) {
  $stmtStat->bind_param("s", $today);
  $stmtStat->execute();
  $resStat = $stmtStat->get_result();
  if ($resStat) $stat_today = (int)($resStat->fetch_assoc()['c'] ?? 0);
  $stmtStat->close();
}

// Branches list
$branch_res = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name ASC");
$branches = [];
if ($branch_res) {
    while ($b = $branch_res->fetch_assoc()) {
        $branches[] = $b['branch_name'];
    }
}

// All active staff for employee dropdown selector
$staff_options = [];
$st_res = $conn->query("SELECT staff_id, full_name, department, branch, job_title, (CASE WHEN photo IS NOT NULL AND LENGTH(photo) > 100 THEN 1 ELSE 0 END) AS has_photo FROM staff WHERE deleted_at IS NULL ORDER BY full_name ASC");
if ($st_res) {
    while ($st = $st_res->fetch_assoc()) {
        $staff_options[] = $st;
    }
}

$selected_staff_id = trim($_GET['staff_id'] ?? '');
$search            = trim($_GET['search'] ?? '');
$from              = trim($_GET['from'] ?? ($_GET['filter_date'] ?? ''));
$to                = trim($_GET['to'] ?? ($_GET['filter_date'] ?? ''));
$selected_branch   = trim($_GET['filter_branch'] ?? '');

// If staff_id not set but search matches an exact staff_id, align them
if (empty($selected_staff_id) && !empty($search)) {
    foreach ($staff_options as $opt) {
        if (strcasecmp($opt['staff_id'], $search) === 0 || strcasecmp($opt['full_name'], $search) === 0) {
            $selected_staff_id = $opt['staff_id'];
            break;
        }
    }
}

// Find employee profile if filtered
$individual_staff = null;
if (!empty($selected_staff_id)) {
    foreach ($staff_options as $opt) {
        if ($opt['staff_id'] === $selected_staff_id) {
            $individual_staff = $opt;
            break;
        }
    }
    if (!$individual_staff) {
        $stmt_one = $conn->prepare("SELECT staff_id, full_name, department, branch, job_title, (CASE WHEN photo IS NOT NULL AND LENGTH(photo) > 100 THEN 1 ELSE 0 END) AS has_photo FROM staff WHERE staff_id = ? LIMIT 1");
        if ($stmt_one) {
            $stmt_one->bind_param("s", $selected_staff_id);
            $stmt_one->execute();
            $individual_staff = $stmt_one->get_result()->fetch_assoc();
            $stmt_one->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="TDS Attendance">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/asset/img/tds_logo.png">
<title>Attendance Report</title>
<link rel="stylesheet" href="/asset/css/style.css">
<style>
    @media (max-width: 1024px) {
        .full-screen-report {
            padding: 16px !important;
            width: 100% !important;
        }
    }
    .full-screen-report .table-scroll {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }
    .full-screen-report .table-scroll table {
        width: 100% !important;
        min-width: 950px;
    }

    .preset-pill {
        padding: 6px 12px;
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #cbd5e1;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
        display: inline-block;
    }
    .preset-pill:hover, .preset-pill.active {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }
    .badge-card {
        background: #ede9fe;
        color: #6d28d9;
        border: 1px solid #ddd6fe;
    }
    .badge-bio {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    .badge-face {
        background: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }
    .badge-device {
        background: #fef3c7;
        color: #b45309;
        border: 1px solid #fde68a;
    }
</style>
</head>
<body class="app-page reports-page">

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content full-screen-report" style="width: calc(100% - var(--sidebar-w)); max-width: 100%; box-sizing: border-box; display: flex; flex-direction: column; min-height: 100vh; padding: 24px 32px;">
  <div style="background: linear-gradient(135deg, #0f172a, #1e293b); color:white; padding:32px; border-radius:24px; margin-bottom:22px;">
    <h2 style="margin:0; font-size:28px;">📊 Attendance Reports &amp; Analytics</h2>
    <p style="opacity:0.75; margin-top:10px;">Select an individual employee, filter date ranges (day-to-day or month-to-month), and export individual Excel records</p>
  </div>

  <div class="cards" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:18px; width:100%; box-sizing:border-box; margin-top:0; margin-bottom:20px;">
    <div class="card"><h3>Total Records</h3><p><?php echo (int)$stat_total; ?></p></div>
    <div class="card"><h3>Today</h3><p><?php echo (int)$stat_today; ?></p></div>
    <div class="card"><h3>Total Hours (All-Time)</h3><p style="font-size: 20px;"><?php echo formatHours($stat_hours); ?></p></div>
  </div>

  <!-- Advanced Report Filter Card -->
  <div style="background:white; border-radius:20px; border:1px solid #e2e8f0; padding:22px; margin-bottom:18px; box-shadow:0 4px 12px rgba(0,0,0,0.03);">
    <form method="GET" id="repFilterForm" style="display:flex; flex-direction:column; gap:14px;">
      
      <!-- Top Row: Employee Selector & Branch Filter -->
      <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <div style="flex:2; min-width:260px;">
          <label style="display:block; margin-bottom:5px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
            👤 Select Individual Employee:
          </label>
          <select name="staff_id" id="repStaffSelect" onchange="document.getElementById('repFilterForm').submit()" style="width:100%; box-sizing:border-box; padding:11px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size:14px; font-weight:600; background:white; cursor:pointer;">
            <option value="">👥 All Employees (Entire Organization)</option>
            <?php foreach ($staff_options as $st): ?>
              <option value="<?php echo htmlspecialchars($st['staff_id']); ?>" <?php echo $selected_staff_id === $st['staff_id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($st['full_name']); ?> (<?php echo htmlspecialchars($st['staff_id']); ?>) - <?php echo htmlspecialchars($st['department'] ?: 'General'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="flex:1; min-width:180px;">
          <label style="display:block; margin-bottom:5px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
            🏢 Branch:
          </label>
          <select name="filter_branch" onchange="document.getElementById('repFilterForm').submit()" style="width:100%; box-sizing:border-box; padding:11px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size:14px; font-weight:600; background:white; cursor:pointer;">
            <option value="">All Branches</option>
            <?php foreach ($branches as $br): ?>
              <option value="<?php echo htmlspecialchars($br); ?>" <?php echo $selected_branch === $br ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($br); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Second Row: Date From, Date To, and Quick Presets -->
      <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <div style="flex:1; min-width:140px;">
          <label style="display:block; margin-bottom:5px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
            📅 From Date:
          </label>
          <input type="date" name="from" id="repFrom" value="<?php echo htmlspecialchars($from); ?>" style="width:100%; box-sizing:border-box; padding:10px 12px; border-radius:10px; border:1px solid #cbd5e1; font-size:14px;">
        </div>

        <div style="flex:1; min-width:140px;">
          <label style="display:block; margin-bottom:5px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
            📅 To Date:
          </label>
          <input type="date" name="to" id="repTo" value="<?php echo htmlspecialchars($to); ?>" style="width:100%; box-sizing:border-box; padding:10px 12px; border-radius:10px; border:1px solid #cbd5e1; font-size:14px;">
        </div>

        <!-- Quick Presets -->
        <div style="flex:2; min-width:280px;">
          <label style="display:block; margin-bottom:6px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
            ⚡ Quick Date Range:
          </label>
          <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <button type="button" class="preset-pill" onclick="applyRepDatePreset('today')">Today</button>
            <button type="button" class="preset-pill" onclick="applyRepDatePreset('yesterday')">Yesterday</button>
            <button type="button" class="preset-pill" onclick="applyRepDatePreset('this_week')">This Week</button>
            <button type="button" class="preset-pill" onclick="applyRepDatePreset('this_month')">This Month</button>
            <button type="button" class="preset-pill" onclick="applyRepDatePreset('last_month')">Last Month</button>
            <button type="button" class="preset-pill" onclick="applyRepDatePreset('all')">All Time</button>
          </div>
        </div>
      </div>

      <!-- Actions Row -->
      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:6px;">
        <button type="submit" style="padding:11px 24px; border-radius:10px; background:var(--primary); color:white; border:none; font-weight:700; font-size:14px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
          <span>📊</span> Generate Report
        </button>

        <a href="reports.php" style="padding:11px 18px; border-radius:10px; background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; text-decoration:none; font-weight:600; font-size:13px;">
          Reset
        </a>

        <button type="button" class="print-btn" onclick="window.print()" style="padding:11px 18px; border-radius:10px; background:white; color:#334155; border:1px solid #cbd5e1; font-weight:600; font-size:13px; cursor:pointer;">
          🖨️ Print
        </button>

        <?php
          $repExport = "backup.php?action=attendance_excel";
          if (!empty($selected_staff_id)) $repExport .= "&staff_id=" . urlencode($selected_staff_id);
          if (!empty($search)) $repExport .= "&search=" . urlencode($search);
          if (!empty($from)) $repExport .= "&from=" . urlencode($from);
          if (!empty($to)) $repExport .= "&to=" . urlencode($to);
          if (!empty($selected_branch)) $repExport .= "&branch=" . urlencode($selected_branch);
        ?>
        <a href="<?php echo htmlspecialchars($repExport); ?>" target="_blank" style="padding:11px 22px; border-radius:10px; background:linear-gradient(135deg, #10b981, #059669); color:white; text-decoration:none; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(16,185,129,0.25); margin-left:auto;">
          <span>📊</span> Export to Excel Sheet (.csv)
        </a>
      </div>
    </form>
  </div>

  <?php
  // ── PREPARE DATA QUERY ──────────────────────────────────────────
  $query = "SELECT a.id, a.staff_id, s.full_name, s.department, s.branch, s.job_title,
                   a.clock_in, a.clock_out, a.source, a.status, a.total_hours, a.branch_in, a.branch_out
            FROM attendance a
            JOIN staff s ON a.staff_id = s.staff_id WHERE s.deleted_at IS NULL";
  $params = [];
  $types = "";

  if (!empty($selected_staff_id)) {
      $query .= " AND a.staff_id = ?";
      $params[] = $selected_staff_id;
      $types .= "s";
  } elseif (!empty($search)) {
      $query .= " AND (s.full_name LIKE ? OR a.staff_id LIKE ?)";
      $sTerm = "%{$search}%";
      $params[] = $sTerm;
      $params[] = $sTerm;
      $types .= "ss";
  }

  if (!empty($from) && !empty($to)) {
      $query .= " AND DATE(a.clock_in) BETWEEN ? AND ?";
      $params[] = $from;
      $params[] = $to;
      $types .= "ss";
  } elseif (!empty($from)) {
      $query .= " AND DATE(a.clock_in) >= ?";
      $params[] = $from;
      $types .= "s";
  } elseif (!empty($to)) {
      $query .= " AND DATE(a.clock_in) <= ?";
      $params[] = $to;
      $types .= "s";
  }

  if (!empty($selected_branch)) {
      $query .= " AND (s.branch = ? OR a.branch_in = ? OR a.branch_out = ?)";
      $params[] = $selected_branch;
      $params[] = $selected_branch;
      $params[] = $selected_branch;
      $types .= "sss";
  }

  $query .= " ORDER BY a.clock_in DESC";

  if (!empty($params)) {
      $stmt = $conn->prepare($query);
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $result = $stmt->get_result();
  } else {
      $result = $conn->query($query);
  }

  // Pre-aggregate statistics
  $recordsList = [];
  $totalRecords = 0;
  $totalHoursAll = 0.0;
  $daysSet = [];
  $completedCount = 0;
  $missedCount = 0;

  if ($result && $result->num_rows > 0) {
      while ($r = $result->fetch_assoc()) {
          $recordsList[] = $r;
          $totalRecords++;
          $h = (float)($r['total_hours'] ?? 0);
          $totalHoursAll += $h;
          $datePart = date('Y-m-d', strtotime($r['clock_in']));
          $daysSet[$datePart] = true;
          if (($r['status'] ?? '') === 'missed_out') {
              $missedCount++;
          } elseif (!empty($r['clock_out'])) {
              $completedCount++;
          }
      }
  }
  $distinctDays = count($daysSet);
  ?>

  <!-- Individual Staff Summary Profile Card -->
  <?php if ($individual_staff): ?>
  <div style="background:linear-gradient(135deg, #1e1b4b, #312e81); color:white; border-radius:24px; padding:24px 28px; margin-bottom:20px; box-shadow:0 12px 28px rgba(49,46,129,0.25);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
      <div style="display:flex; align-items:center; gap:18px;">
        <div style="width:64px; height:64px; border-radius:50%; background:rgba(255,255,255,0.15); border:2px solid rgba(255,255,255,0.4); display:flex; align-items:center; justify-content:center; font-size:28px; overflow:hidden;">
          <?php if (!empty($individual_staff['has_photo'])): ?>
            <img src="photo.php?type=staff&id=<?php echo urlencode($individual_staff['staff_id']); ?>" style="width:100%; height:100%; object-fit:cover;" alt="Photo">
          <?php else: ?>
            <span>👤</span>
          <?php endif; ?>
        </div>
        <div>
          <h3 style="margin:0 0 4px; font-size:22px; color:white; font-weight:800;">
            <?php echo htmlspecialchars($individual_staff['full_name']); ?>
          </h3>
          <p style="margin:0; opacity:0.85; font-size:14px;">
            Staff ID: <code style="background:rgba(255,255,255,0.2); padding:2px 8px; border-radius:6px;"><?php echo htmlspecialchars($individual_staff['staff_id']); ?></code>
            • <?php echo htmlspecialchars($individual_staff['job_title'] ?: 'Employee'); ?>
            • <?php echo htmlspecialchars($individual_staff['department'] ?: 'Staff'); ?>
            • 📍 <?php echo htmlspecialchars($individual_staff['branch'] ?: 'Main Branch'); ?>
          </p>
        </div>
      </div>

      <!-- Quick individual export button -->
      <div>
        <a href="<?php echo htmlspecialchars($repExport); ?>" target="_blank" style="padding:10px 20px; background:#10b981; color:white; border-radius:12px; text-decoration:none; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 4px 12px rgba(16,185,129,0.35);">
          <span>📥</span> Download <?php echo htmlspecialchars(explode(' ', $individual_staff['full_name'])[0]); ?>'s Excel (.csv)
        </a>
      </div>
    </div>

    <!-- Quick Stats for Individual Employee in Selected Period -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:12px; margin-top:20px; padding-top:16px; border-top:1px solid rgba(255,255,255,0.15);">
      <div style="background:rgba(255,255,255,0.08); padding:12px 16px; border-radius:14px; text-align:center;">
        <span style="font-size:11px; opacity:0.75; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; display:block;">Days Present</span>
        <strong style="font-size:22px; color:#a7f3d0;"><?php echo $distinctDays; ?></strong>
      </div>
      <div style="background:rgba(255,255,255,0.08); padding:12px 16px; border-radius:14px; text-align:center;">
        <span style="font-size:11px; opacity:0.75; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; display:block;">Total Hours</span>
        <strong style="font-size:20px; color:#fed7aa;"><?php echo formatHours($totalHoursAll); ?></strong>
      </div>
      <div style="background:rgba(255,255,255,0.08); padding:12px 16px; border-radius:14px; text-align:center;">
        <span style="font-size:11px; opacity:0.75; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; display:block;">Avg Hours / Day</span>
        <strong style="font-size:20px; color:#e0e7ff;"><?php echo $distinctDays > 0 ? number_format($totalHoursAll / $distinctDays, 1) . ' hrs' : '0 hrs'; ?></strong>
      </div>
      <div style="background:rgba(255,255,255,0.08); padding:12px 16px; border-radius:14px; text-align:center;">
        <span style="font-size:11px; opacity:0.75; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; display:block;">Completed Shifts</span>
        <strong style="font-size:20px; color:#bfdbfe;"><?php echo $completedCount; ?></strong>
      </div>
      <div style="background:rgba(255,255,255,0.08); padding:12px 16px; border-radius:14px; text-align:center;">
        <span style="font-size:11px; opacity:0.75; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; display:block;">Missed Clock-Out</span>
        <strong style="font-size:20px; color:#fca5a5;"><?php echo $missedCount; ?></strong>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Report Data Table -->
  <div class="table-card" style="box-shadow:none; border:1px solid #e2e8f0; border-radius:16px; margin-top:8px; padding:0; background:#fff;">
    <div class="table-scroll">
      <table class="responsive-table" style="width:100%; margin:0; border-collapse:collapse;">
        <thead>
          <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Log ID</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Staff ID</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Full Name</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Department</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Date</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Clock In</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Clock Out</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Method</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Status</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Total Hours</th>
          </tr>
        </thead>
        <tbody>

    <?php
    if (!empty($recordsList)) {
        foreach ($recordsList as $row) {
            $statusClass = strtolower($row['status'] ?? 'in');
            $hours = floatval($row['total_hours']);
            $dateFormatted = date("M d, Y (D)", strtotime($row['clock_in']));
            
            $src = strtolower(trim((string)($row['source'] ?? '')));
            if ($src === 'card') {
                $srcLabel = '💳 E-CARD';
                $srcBadgeClass = 'badge-card';
            } elseif ($src === 'fingerprint') {
                $srcLabel = '👆 THUMBPRINT';
                $srcBadgeClass = 'badge-bio';
            } elseif ($src === 'device') {
                $srcLabel = '📠 DEVICE FP';
                $srcBadgeClass = 'badge-device';
            } elseif ($src === 'mobile') {
                $srcLabel = '📸 FACE SCAN';
                $srcBadgeClass = 'badge-face';
            } else {
                $srcLabel = strtoupper($src ?: 'WEB');
                $srcBadgeClass = 'badge-info';
            }

            $b_in = htmlspecialchars($row['branch_in'] ?? 'Office');
            $b_out = htmlspecialchars($row['branch_out'] ?? 'Office');
            
            $clockInFormatted = date("h:i A", strtotime($row['clock_in']));
            $clockInHtml = "{$clockInFormatted}<br><small style='color:#4f46e5;font-weight:600;'>📍 {$b_in}</small>";
            $clockOutHtml = "—";
            if (!empty($row['clock_out'])) {
                $clockOutFormatted = date("h:i A", strtotime($row['clock_out']));
                $clockOutHtml = "{$clockOutFormatted}<br><small style='color:#4f46e5;font-weight:600;'>📍 {$b_out}</small>";
            }

            $hoursFormatted = !empty($row['clock_out']) ? formatHours($hours) : "<span style='color:#f59e0b;font-weight:600;'>In Progress</span>";

            echo "<tr>
                    <td data-label='Log ID' style='padding:12px 16px;'>{$row['id']}</td>
                    <td data-label='Staff ID' style='padding:12px 16px;'><code style='background:#f1f5f9;padding:2px 6px;border-radius:4px;'>{$row['staff_id']}</code></td>
                    <td data-label='Full Name' style='padding:12px 16px;'><strong>" . htmlspecialchars($row['full_name']) . "</strong></td>
                    <td data-label='Department' style='padding:12px 16px;'>" . htmlspecialchars($row['department'] ?? '—') . "</td>
                    <td data-label='Date' style='padding:12px 16px;'>{$dateFormatted}</td>
                    <td data-label='Clock In' style='padding:12px 16px;'>{$clockInHtml}</td>
                    <td data-label='Clock Out' style='padding:12px 16px;'>{$clockOutHtml}</td>
                    <td data-label='Method' style='padding:12px 16px;'><span class='badge {$srcBadgeClass}' style='font-size:11px; font-weight:700;'>{$srcLabel}</span></td>
                    <td data-label='Status' style='padding:12px 16px;' class='status {$statusClass}'>" . (($row['status'] ?? 'In') === 'missed_out' ? 'missed(clockout)' : ($row['status'] ?? 'In')) . "</td>
                    <td data-label='Total Hours' style='padding:12px 16px;'><strong>{$hoursFormatted}</strong></td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='10' style='text-align:center;padding:36px;color:#94a3b8;'>No attendance records found for the selected criteria.</td></tr>";
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        try { $stmt->close(); } catch (Throwable $e) {}
    }
    ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="summary" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:16px; padding:12px 0;">
    <div>Total Records: <strong><?php echo $totalRecords; ?></strong> | Total Days Worked: <strong><?php echo $distinctDays; ?></strong></div>
    <div>Total Hours Accumulated: <strong><?php echo formatHours($totalHoursAll); ?></strong></div>
  </div>

  <div class="footer" style="text-align:center; margin-top:auto; padding:28px 0 16px; color:var(--text-muted); font-size:13px; border-top:1px solid var(--border); width:100%;">
    &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon
  </div>
</div>

<script>
function applyRepDatePreset(preset) {
    const today = new Date();
    const fromInput = document.getElementById('repFrom');
    const toInput = document.getElementById('repTo');
    
    function fmt(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    if (preset === 'today') {
        const s = fmt(today);
        fromInput.value = s;
        toInput.value = s;
    } else if (preset === 'yesterday') {
        const yest = new Date(today);
        yest.setDate(yest.getDate() - 1);
        const s = fmt(yest);
        fromInput.value = s;
        toInput.value = s;
    } else if (preset === 'this_week') {
        const first = new Date(today);
        const day = first.getDay();
        const diff = first.getDate() - day + (day === 0 ? -6 : 1);
        first.setDate(diff);
        fromInput.value = fmt(first);
        toInput.value = fmt(today);
    } else if (preset === 'this_month') {
        const first = new Date(today.getFullYear(), today.getMonth(), 1);
        fromInput.value = fmt(first);
        toInput.value = fmt(today);
    } else if (preset === 'last_month') {
        const first = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const last = new Date(today.getFullYear(), today.getMonth(), 0);
        fromInput.value = fmt(first);
        toInput.value = fmt(last);
    } else if (preset === 'all') {
        fromInput.value = '';
        toInput.value = '';
    }
    document.getElementById('repFilterForm').submit();
}
</script>
</body>
</html>
