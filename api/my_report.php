<?php
include(__DIR__ . "/includes/config.php");

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

if (empty($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

$staff_id = $_SESSION['staff_id'];
$month = isset($_GET['month']) ? trim($_GET['month']) : '';
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));

$stmt = $conn->prepare("
    SELECT DATE(clock_in) AS day, 
           COUNT(*) AS sessions, 
           SUM(CASE WHEN total_hours IS NULL THEN 0 ELSE total_hours END) AS hours,
           GROUP_CONCAT(DISTINCT branch_in SEPARATOR ', ') AS branches_in,
           GROUP_CONCAT(DISTINCT branch_out SEPARATOR ', ') AS branches_out
    FROM attendance
    WHERE staff_id = ? AND DATE(clock_in) BETWEEN ? AND ?
    GROUP BY DATE(clock_in)
    ORDER BY day DESC
");
$stmt->bind_param("sss", $staff_id, $start, $end);
$stmt->execute();
$res = $stmt->get_result();

$daily = [];
$totalDays = 0;
$totalSessions = 0;
$totalHours = 0.0;
while ($r = $res->fetch_assoc()) {
    $daily[] = $r;
    $totalDays++;
    $totalSessions += (int)$r['sessions'];
    $totalHours += (float)($r['hours'] ?? 0);
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Report</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body class="app-page report-page">

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
  <h2>My Report</h2>
  <p class="subtitle">Summary for <?php echo htmlspecialchars($month); ?></p>

  <form method="GET">
    <label>Month:</label>
    <input type="month" name="month" value="<?php echo htmlspecialchars($month); ?>">
    <button type="submit">View</button>
    <a href="my_report.php" style="margin-left:10px; color:var(--primary); font-weight:600;">This month</a>
    <button type="button" class="print-btn" onclick="window.print()">🖨️ Print</button>
  </form>

  <div class="cards">
    <div class="card">
      <h3>Total Hours</h3>
      <p style="font-size: 20px;"><?php echo formatHours($totalHours); ?></p>
    </div>
    <div class="card">
      <h3>Attendance Days</h3>
      <p><?php echo htmlspecialchars((string)$totalDays); ?></p>
    </div>
    <div class="card">
      <h3>Sessions</h3>
      <p><?php echo htmlspecialchars((string)$totalSessions); ?></p>
    </div>
  </div>

  <div class="table-card">
    <div class="table-scroll">
      <table class="responsive-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Branches Visited</th>
            <th>Sessions</th>
            <th>Total Hours</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($daily)): ?>
            <?php foreach ($daily as $row): ?>
              <?php
                $bIn = array_filter(array_map('trim', explode(',', $row['branches_in'] ?? '')));
                $bOut = array_filter(array_map('trim', explode(',', $row['branches_out'] ?? '')));
                $allBranches = array_unique(array_merge($bIn, $bOut));
                $branchDisp = empty($allBranches) ? "<span style='color:#94a3b8;'>—</span>" : htmlspecialchars(implode(', ', $allBranches));
              ?>
              <tr>
                <td data-label="Date"><?php echo htmlspecialchars($row['day']); ?></td>
                <td data-label="Branches Visited"><span style="font-weight:600; color:#4f46e5;">📍 <?php echo $branchDisp; ?></span></td>
                <td data-label="Sessions"><?php echo htmlspecialchars((string)$row['sessions']); ?></td>
                <td data-label="Total Hours"><?php echo formatHours((float)($row['hours'] ?? 0)); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4" style="text-align:center;">No records found for this month</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="footer">&copy; <?php echo date("Y"); ?> Attendance System</div>
</div>

</body>
</html>

