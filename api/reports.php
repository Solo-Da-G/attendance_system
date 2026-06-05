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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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

  <form method="GET">
    <label>Filter by Date:</label>
    <input type="date" name="filter_date" value="<?php echo isset($_GET['filter_date']) ? htmlspecialchars($_GET['filter_date']) : ''; ?>">
    <button type="submit">View</button>
    <a href="reports.php" style="margin-left:10px; color:var(--primary); font-weight:500;">Reset</a>
  </form>

  <div class="print-box">
    <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>
  </div>

  <table>
    <tr>
      <th>ID</th>
      <th>Staff ID</th>
      <th>Full Name</th>
      <th>Department</th>
      <th>Clock In</th>
      <th>Clock Out</th>
      <th>Source</th>
      <th>Status</th>
      <th>Total Hours</th>
    </tr>

    <?php
    $totalRecords = 0;
    $totalHoursAll = 0;

    if (!empty($_GET['filter_date'])) {
        $filter_date = $_GET['filter_date'];
        $stmt = $conn->prepare("
          SELECT a.id, a.staff_id, s.full_name, s.department, a.clock_in, a.clock_out, a.source, a.status, a.total_hours
          FROM attendance a
          JOIN staff s ON a.staff_id = s.staff_id
          WHERE DATE(a.clock_in) = ?
          ORDER BY a.clock_in DESC
        ");
        $stmt->bind_param("s", $filter_date);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("
          SELECT a.id, a.staff_id, s.full_name, s.department, a.clock_in, a.clock_out, a.source, a.status, a.total_hours
          FROM attendance a
          JOIN staff s ON a.staff_id = s.staff_id
          ORDER BY a.clock_in DESC
        ");
    }

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $statusClass = strtolower($row['status'] ?? 'in');
            $hours = floatval($row['total_hours']);
            $src = strtolower(trim((string)($row['source'] ?? '')));
            $srcLabel = $src === 'device' ? 'DEVICE' : ($src === 'mobile' ? 'MOBILE' : 'UNKNOWN');
            $srcBadge = $src === 'device' ? 'badge-info' : ($src === 'mobile' ? 'badge-success' : 'badge-warning');
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['staff_id']}</td>
                    <td>" . htmlspecialchars($row['full_name']) . "</td>
                    <td>{$row['department']}</td>
                    <td>{$row['clock_in']}</td>
                    <td>" . ($row['clock_out'] ?? '—') . "</td>
                    <td><span class='badge {$srcBadge}'>{$srcLabel}</span></td>
                    <td class='status {$statusClass}'>" . ($row['status'] ?? 'In') . "</td>
                    <td>" . formatHours($hours) . "</td>
                  </tr>";
            $totalRecords++;
            $totalHoursAll += $hours;
        }
    } else {
        echo "<tr><td colspan='9' style='text-align:center;'>No attendance records found.</td></tr>";
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        try { $stmt->close(); } catch (Throwable $e) {}
    }
    ?>
  </table>

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
