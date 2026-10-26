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
</head>
<body>

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
  <div style="background: linear-gradient(135deg, #0f172a, #1e293b); color:white; padding:32px; border-radius:24px; margin-bottom:22px;">
    <h2 style="margin:0; font-size:28px;">📊 Attendance Report</h2>
    <p style="opacity:0.75; margin-top:10px;">Filter, print and review attendance summary</p>
  </div>

  <div class="cards" style="margin-top:0; margin-bottom:18px;">
    <div class="card"><h3>Total Records</h3><p><?php echo (int)$stat_total; ?></p></div>
    <div class="card"><h3>Today</h3><p><?php echo (int)$stat_today; ?></p></div>
    <div class="card"><h3>Total Hours</h3><p style="font-size: 20px;"><?php echo formatHours($stat_hours); ?></p></div>
  </div>

  <?php
  $branch_res = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name ASC");
  $branches = [];
  if ($branch_res) {
      while ($b = $branch_res->fetch_assoc()) {
          $branches[] = $b['branch_name'];
      }
  }
  $selected_branch = $_GET['filter_branch'] ?? '';
  ?>
  <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
    <div>
      <label style="display:block; margin-bottom:5px; font-weight:600;">Filter by Date:</label>
      <input type="date" name="filter_date" value="<?php echo isset($_GET['filter_date']) ? htmlspecialchars($_GET['filter_date']) : ''; ?>" style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1;">
    </div>
    <div>
      <label style="display:block; margin-bottom:5px; font-weight:600;">Branch:</label>
      <select name="filter_branch" style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; min-width: 150px;">
          <option value="">All Branches</option>
          <?php foreach ($branches as $br): ?>
              <option value="<?php echo htmlspecialchars($br); ?>" <?php echo $selected_branch === $br ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($br); ?>
              </option>
          <?php endforeach; ?>
      </select>
    </div>
    <div>
      <button type="submit" style="padding: 10px 20px; border-radius: 8px;">View</button>
      <a href="reports.php" style="margin-left:10px; color:var(--primary); font-weight:600; text-decoration:none;">Reset</a>
    </div>
  </form>

  <div class="print-box">
    <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>
  </div>

  <div style="background:#f0f9ff; color:#0369a1; border:1px solid #bae6fd; padding:10px 14px; border-radius:12px; font-size:13px; font-weight:600; margin: 16px 0 12px; display:flex; align-items:center; gap:8px;">
    <span>👉</span>
    <span>Swipe table horizontally to review all columns (department, GPS location, source, status, hours).</span>
  </div>

  <div class="table-card" style="box-shadow:none; border:1px solid #e2e8f0; border-radius:16px; margin-top:8px; padding:0; overflow:hidden; background:#fff;">
    <div class="table-scroll" style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
      <table class="responsive-table" style="min-width:850px; width:100%; margin:0; border-collapse:collapse;">
        <thead>
          <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">ID</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Staff ID</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Full Name</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Department</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Clock In</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Clock Out</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Source</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Status</th>
            <th style="padding:14px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Total Hours</th>
          </tr>
        </thead>
        <tbody>

    <?php
    $totalRecords = 0;
    $totalHoursAll = 0;

    $filter_date = $_GET['filter_date'] ?? '';
    $filter_branch = $_GET['filter_branch'] ?? '';
    
    $query = "SELECT a.id, a.staff_id, s.full_name, s.department, a.clock_in, a.clock_out, a.source, a.status, a.total_hours, a.branch_in, a.branch_out
              FROM attendance a
              JOIN staff s ON a.staff_id = s.staff_id WHERE s.deleted_at IS NULL";
    $params = [];
    $types = "";

    if (!empty($filter_date)) {
        $query .= " AND DATE(a.clock_in) = ?";
        $params[] = $filter_date;
        $types .= "s";
    }
    if (!empty($filter_branch)) {
        $query .= " AND (a.branch_in = ? OR a.branch_out = ?)";
        $params[] = $filter_branch;
        $params[] = $filter_branch;
        $types .= "ss";
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

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $statusClass = strtolower($row['status'] ?? 'in');
            $hours = floatval($row['total_hours']);
            $src = strtolower(trim((string)($row['source'] ?? '')));
            $srcLabel = $src === 'device' ? 'DEVICE' : ($src === 'mobile' ? 'MOBILE' : 'UNKNOWN');
            $srcBadge = $src === 'device' ? 'badge-info' : ($src === 'mobile' ? 'badge-success' : 'badge-warning');
            $b_in = htmlspecialchars($row['branch_in'] ?? 'Unknown Branch');
            $b_out = htmlspecialchars($row['branch_out'] ?? 'Unknown Branch');
            
            $clockInHtml = "{$row['clock_in']}<br><small style='color:#4f46e5;font-weight:600;'>📍 {$b_in}</small>";
            $clockOutHtml = "—";
            if (!empty($row['clock_out'])) {
                $clockOutHtml = "{$row['clock_out']}<br><small style='color:#4f46e5;font-weight:600;'>📍 {$b_out}</small>";
            }

            echo "<tr>
                    <td data-label='ID' style='padding:12px 16px;'>{$row['id']}</td>
                    <td data-label='Staff ID' style='padding:12px 16px;'><code style='background:#f1f5f9;padding:2px 6px;border-radius:4px;'>{$row['staff_id']}</code></td>
                    <td data-label='Full Name' style='padding:12px 16px;'><strong>" . htmlspecialchars($row['full_name']) . "</strong></td>
                    <td data-label='Department' style='padding:12px 16px;'>" . htmlspecialchars($row['department'] ?? '—') . "</td>
                    <td data-label='Clock In' style='padding:12px 16px;'>{$clockInHtml}</td>
                    <td data-label='Clock Out' style='padding:12px 16px;'>{$clockOutHtml}</td>
                    <td data-label='Source' style='padding:12px 16px;'><span class='badge {$srcBadge}'>{$srcLabel}</span></td>
                    <td data-label='Status' style='padding:12px 16px;' class='status {$statusClass}'>" . (($row['status'] ?? 'In') === 'missed_out' ? 'missed(clockout)' : ($row['status'] ?? 'In')) . "</td>
                    <td data-label='Total Hours' style='padding:12px 16px;'><strong>" . formatHours($hours) . "</strong></td>
                  </tr>";
            $totalRecords++;
            $totalHoursAll += $hours;
        }
    } else {
        echo "<tr><td colspan='9' style='text-align:center;padding:32px;color:#94a3b8;'>No attendance records found.</td></tr>";
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        try { $stmt->close(); } catch (Throwable $e) {}
    }
    ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="summary">
    <p>Total Records: <?php echo $totalRecords; ?></p>
    <p>Total Hours (All Staff): <?php echo formatHours($totalHoursAll); ?></p>
  </div>
</div>

<div class="footer">
  &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Collins
</div>

</body>
</html>
