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
$stat_open = 0;

$r = $conn->query("SELECT COUNT(*) AS c FROM attendance");
if ($r && !is_bool($r)) $stat_total = (int)($r->fetch_assoc()['c'] ?? 0);

$stmtStat = $conn->prepare("SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in) = ?");
if ($stmtStat) {
    $stmtStat->bind_param("s", $today);
    $stmtStat->execute();
    $resStat = $stmtStat->get_result();
    if ($resStat) $stat_today = (int)($resStat->fetch_assoc()['c'] ?? 0);
    $stmtStat->close();
}

$r2 = $conn->query("SELECT COUNT(*) AS c FROM attendance WHERE clock_out IS NULL");
if ($r2 && !is_bool($r2)) $stat_open = (int)($r2->fetch_assoc()['c'] ?? 0);

function formatAttendanceHours($decimal) {
    if (!$decimal || $decimal <= 0) return "—";
    $h = floor($decimal);
    $m = floor(($decimal - $h) * 60);
    $str = "";
    if ($h > 0) $str .= "{$h}h ";
    if ($m > 0 || $h == 0) $str .= "{$m}m";
    return trim($str);
}

// Fetch branches for filter
$branch_res = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name ASC");
$branches = [];
if ($branch_res) {
    while ($b = $branch_res->fetch_assoc()) {
        $branches[] = $b['branch_name'];
    }
}

// Fetch staff for search dropdown helper
$staff_list = [];
$st_res = $conn->query("SELECT staff_id, full_name FROM staff WHERE deleted_at IS NULL ORDER BY full_name ASC");
if ($st_res) {
    while ($s = $st_res->fetch_assoc()) {
        $staff_list[] = $s;
    }
}

$search          = trim($_GET['search'] ?? '');
$from            = trim($_GET['from'] ?? '');
$to              = trim($_GET['to'] ?? '');
$selected_branch = trim($_GET['filter_branch'] ?? '');
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
<title>Attendance Records</title>
<link rel="stylesheet" href="/asset/css/style.css">
<style>
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
<body>

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<!-- Main Content -->
<div class="content">
  <div style="background: linear-gradient(135deg, #0f172a, #1e293b); color:white; padding:32px; border-radius:24px; margin-bottom:22px;">
    <h2 style="margin:0; font-size:28px;">🕒 Attendance Records &amp; Logs</h2>
    <p style="opacity:0.75; margin-top:10px;">Inspect individual staff clock-in/out records, filter by date range, and export to Excel</p>
  </div>

  <div class="cards" style="margin-top:0; margin-bottom:18px;">
    <div class="card"><h3>Total Records</h3><p><?php echo (int)$stat_total; ?></p></div>
    <div class="card"><h3>Today</h3><p><?php echo (int)$stat_today; ?></p></div>
    <div class="card"><h3>Currently In</h3><p><?php echo (int)$stat_open; ?></p></div>
  </div>

  <!-- Filter Card -->
  <div style="background:white; border-radius:20px; border:1px solid #e2e8f0; padding:20px; margin-bottom:18px; box-shadow:0 4px 12px rgba(0,0,0,0.03);">
    <form method="GET" id="attFilterForm" style="display:flex; flex-direction:column; gap:14px;">
      
      <!-- Top Row: Staff Search & Branch -->
      <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <div style="flex:2; min-width:240px; position:relative;">
          <label style="display:block; margin-bottom:5px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
            👤 Employee Search (Name or ID):
          </label>
          <input type="text" name="search" id="staffSearchInput" list="staffDataList" value="<?php echo htmlspecialchars($search); ?>" placeholder="Type staff name or Staff ID (e.g. John Doe, EMP001)..." style="width:100%; box-sizing:border-box; padding:11px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size:14px; font-weight:600;">
          <datalist id="staffDataList">
            <?php foreach ($staff_list as $st): ?>
              <option value="<?php echo htmlspecialchars($st['full_name']); ?>">Staff ID: <?php echo htmlspecialchars($st['staff_id']); ?></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div style="flex:1; min-width:180px;">
          <label style="display:block; margin-bottom:5px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
            🏢 Branch:
          </label>
          <select name="filter_branch" style="width:100%; box-sizing:border-box; padding:11px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size:14px; font-weight:600; background:white; cursor:pointer;">
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
            📅 Date From:
          </label>
          <input type="date" name="from" id="dateFrom" value="<?php echo htmlspecialchars($from); ?>" style="width:100%; box-sizing:border-box; padding:10px 12px; border-radius:10px; border:1px solid #cbd5e1; font-size:14px;">
        </div>

        <div style="flex:1; min-width:140px;">
          <label style="display:block; margin-bottom:5px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
            📅 Date To:
          </label>
          <input type="date" name="to" id="dateTo" value="<?php echo htmlspecialchars($to); ?>" style="width:100%; box-sizing:border-box; padding:10px 12px; border-radius:10px; border:1px solid #cbd5e1; font-size:14px;">
        </div>

        <!-- Quick Range Presets -->
        <div style="flex:2; min-width:280px;">
          <label style="display:block; margin-bottom:6px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
            ⚡ Quick Date Range:
          </label>
          <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <button type="button" class="preset-pill" onclick="applyDatePreset('today')">Today</button>
            <button type="button" class="preset-pill" onclick="applyDatePreset('yesterday')">Yesterday</button>
            <button type="button" class="preset-pill" onclick="applyDatePreset('this_week')">This Week</button>
            <button type="button" class="preset-pill" onclick="applyDatePreset('this_month')">This Month</button>
            <button type="button" class="preset-pill" onclick="applyDatePreset('last_month')">Last Month</button>
            <button type="button" class="preset-pill" onclick="applyDatePreset('all')">All Time</button>
          </div>
        </div>
      </div>

      <!-- Action Buttons Row -->
      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:4px;">
        <button type="submit" style="padding:11px 24px; border-radius:10px; background:var(--primary); color:white; border:none; font-weight:700; font-size:14px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
          <span>🔍</span> Filter Records
        </button>

        <a href="attendance.php" style="padding:11px 18px; border-radius:10px; background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; text-decoration:none; font-weight:600; font-size:13px;">
          Reset
        </a>

        <button type="button" class="print-btn" onclick="window.print()" style="padding:11px 18px; border-radius:10px; background:white; color:#334155; border:1px solid #cbd5e1; font-weight:600; font-size:13px; cursor:pointer;">
          🖨️ Print
        </button>

        <?php
          $exportUrl = "backup.php?action=attendance_excel";
          if (!empty($search)) $exportUrl .= "&search=" . urlencode($search);
          if (!empty($from)) $exportUrl .= "&from=" . urlencode($from);
          if (!empty($to)) $exportUrl .= "&to=" . urlencode($to);
          if (!empty($selected_branch)) $exportUrl .= "&branch=" . urlencode($selected_branch);
        ?>
        <a href="<?php echo htmlspecialchars($exportUrl); ?>" target="_blank" style="padding:11px 20px; border-radius:10px; background:linear-gradient(135deg, #10b981, #059669); color:white; text-decoration:none; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(16,185,129,0.25); margin-left:auto;">
          <span>📊</span> Export Filtered to Excel (.csv)
        </a>
      </div>
    </form>
  </div>

  <?php if (!empty($search)): ?>
  <div style="background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; padding:12px 16px; border-radius:14px; font-weight:600; font-size:14px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
    <span>🔍 Showing individual staff records matching: <strong>"<?php echo htmlspecialchars($search); ?>"</strong></span>
    <span style="font-size:12px; color:#3b82f6;">(Click 'Export to Excel' above to download this staff's complete spreadsheet)</span>
  </div>
  <?php endif; ?>

  <div class="table-card" style="box-shadow:none; border:1px solid #e2e8f0; border-radius:16px; margin-top:8px; padding:0; background:#fff;">
    <div class="table-scroll">
      <table class="responsive-table" style="width:100%; margin:0; border-collapse:collapse;">
        <thead>
          <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
            <th>Employee Name</th>
            <th>Staff ID</th>
            <th>Job Title</th>
            <th>Branch</th>
            <th>Date</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Hours</th>
            <th>Method</th>
            <th>Selfie</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>

    <?php
    $query = "SELECT s.full_name, s.staff_id, s.job_title, s.branch, a.id, a.clock_in, a.clock_out, a.status, a.source, a.total_hours,
                     (a.photo_in IS NOT NULL AND a.photo_in != '') AS has_photo_in, 
                     (a.photo_out IS NOT NULL AND a.photo_out != '') AS has_photo_out, 
                     a.branch_in, a.branch_out, a.lat_in, a.lng_in, a.lat_out, a.lng_out, a.is_geofenced
              FROM attendance a
              JOIN staff s ON a.staff_id = s.staff_id WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $query .= " AND (s.full_name LIKE ? OR s.staff_id LIKE ? OR s.job_title LIKE ?)";
        $sTerm = "%{$search}%";
        $params[] = $sTerm;
        $params[] = $sTerm;
        $params[] = $sTerm;
        $types .= "sss";
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

    $query .= " ORDER BY a.clock_in DESC LIMIT 500";

    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }

    $rowCount = 0;
    $totalHoursFiltered = 0.0;

    if ($result && $result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
        $rowCount++;
        $date = date("Y-m-d", strtotime($row['clock_in']));
        $timeIn = date("h:i A", strtotime($row['clock_in']));
        $timeOut = $row['clock_out'] ? date("h:i A", strtotime($row['clock_out'])) : "—";
        $statusClass = strtolower($row['status'] ?? 'in');
        $hoursVal = (float)($row['total_hours'] ?? 0);
        $totalHoursFiltered += $hoursVal;
        $hoursStr = $row['clock_out'] ? formatAttendanceHours($hoursVal) : "In Progress";

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

        $hasSelfie = !empty($row['has_photo_in']) || !empty($row['has_photo_out']);
        $selfieHtml = "<span style='color:var(--text-muted);'>—</span>";
        if ($hasSelfie) {
            $selfieUrl = "/api/photo.php?type=attendance&id=" . (int)$row['id'];
            $selfieHtml = "<img src='{$selfieUrl}' class='photo' style='border-radius:12px;width:44px;height:44px;object-fit:cover;cursor:pointer;' onclick='showFull(\"{$selfieUrl}\")' loading='lazy' alt='Selfie'>";
        }

        $b_in = htmlspecialchars($row['branch_in'] ?? 'Unknown Branch');
        $b_out = htmlspecialchars($row['branch_out'] ?? 'Unknown Branch');
        
        $mapInHtml = "";
        if (!empty($row['lat_in']) && !empty($row['lng_in'])) {
            $mapUrlIn = "https://www.google.com/maps?q=" . urlencode($row['lat_in'] . ',' . $row['lng_in']);
            $mapInHtml = " <a href='{$mapUrlIn}' target='_blank' style='display:inline-flex;align-items:center;gap:2px;font-size:10px;background:#e0e7ff;color:#4338ca;padding:1px 5px;border-radius:4px;text-decoration:none;font-weight:700;' title='Click to view exact location on Google Maps'>🗺️ Map</a>";
        } elseif (($row['source'] ?? '') === 'fingerprint' || ($row['source'] ?? '') === 'mobile') {
            $mapInHtml = " <span style='font-size:10px;color:#ef4444;font-weight:600;' title='No GPS recorded'>⚠️ No GPS</span>";
        }
        $timeInHtml = "{$timeIn}<br><small style='color:#4f46e5;font-weight:600;'>📍 {$b_in}</small>{$mapInHtml}";

        $timeOutHtml = "—";
        if ($row['clock_out']) {
            $mapOutHtml = "";
            if (!empty($row['lat_out']) && !empty($row['lng_out'])) {
                $mapUrlOut = "https://www.google.com/maps?q=" . urlencode($row['lat_out'] . ',' . $row['lng_out']);
                $mapOutHtml = " <a href='{$mapUrlOut}' target='_blank' style='display:inline-flex;align-items:center;gap:2px;font-size:10px;background:#e0e7ff;color:#4338ca;padding:1px 5px;border-radius:4px;text-decoration:none;font-weight:700;' title='Click to view exact location on Google Maps'>🗺️ Map</a>";
            }
            $timeOutHtml = "{$timeOut}<br><small style='color:#4f46e5;font-weight:600;'>📍 {$b_out}</small>{$mapOutHtml}";
        }

        $displayStatus = ($row['status'] ?? 'In') === 'missed_out' ? 'missed(clockout)' : ($row['status'] ?? 'In');

        echo "<tr>
          <td data-label='Employee Name'><strong>" . htmlspecialchars($row['full_name']) . "</strong></td>
          <td data-label='Staff ID'><code style='background:#f1f5f9;padding:2px 6px;border-radius:4px;'>{$row['staff_id']}</code></td>
          <td data-label='Job Title'>" . htmlspecialchars($row['job_title'] ?? '—') . "</td>
          <td data-label='Branch'>" . htmlspecialchars($row['branch'] ?? '—') . "</td>
          <td data-label='Date'>{$date}</td>
          <td data-label='Time In'>{$timeInHtml}</td>
          <td data-label='Time Out'>{$timeOutHtml}</td>
          <td data-label='Hours'><strong>{$hoursStr}</strong></td>
          <td data-label='Method'><span class='badge {$srcBadgeClass}' style='font-size:11px; font-weight:700;'>{$srcLabel}</span></td>
          <td data-label='Selfie'>{$selfieHtml}</td>
          <td data-label='Status' class='status {$statusClass}'>{$displayStatus}</td>
        </tr>";
      }
    } else {
      echo "<tr><td colspan='11' style='text-align:center;padding:36px;color:#94a3b8;'>No attendance records match your search criteria.</td></tr>";
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
      try { $stmt->close(); } catch (Throwable $e) {}
    }
    ?>
        </tbody>
      </table>
    </div>
  </div>

  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:16px; font-size:13px; color:#64748b;">
    <div>Showing <strong><?php echo (int)$rowCount; ?></strong> record(s) <?php if ($totalHoursFiltered > 0) echo "• Total Hours: <strong>" . number_format($totalHoursFiltered, 2) . " hrs</strong>"; ?></div>
    <div>&copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon</div>
  </div>
</div>

<script>
function showFull(src) {
    if (src) window.open(src, '_blank');
}

function applyDatePreset(preset) {
    const today = new Date();
    const fromInput = document.getElementById('dateFrom');
    const toInput = document.getElementById('dateTo');
    
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
        const day = first.getDay(); // 0 is Sun
        const diff = first.getDate() - day + (day === 0 ? -6 : 1); // Monday
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
    document.getElementById('attFilterForm').submit();
}
</script>
</body>
</html>
