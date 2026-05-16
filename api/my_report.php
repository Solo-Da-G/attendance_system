<?php
include(__DIR__ . "/../includes/config.php");

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
    SELECT DATE(clock_in) AS day, COUNT(*) AS sessions, SUM(CASE WHEN total_hours IS NULL THEN 0 ELSE total_hours END) AS hours
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
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

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
      <p><?php echo htmlspecialchars(number_format($totalHours, 2)); ?></p>
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

  <table>
    <tr>
      <th>Date</th>
      <th>Sessions</th>
      <th>Total Hours</th>
    </tr>
    <?php if (!empty($daily)): ?>
      <?php foreach ($daily as $row): ?>
        <tr>
          <td><?php echo htmlspecialchars($row['day']); ?></td>
          <td><?php echo htmlspecialchars((string)$row['sessions']); ?></td>
          <td><?php echo htmlspecialchars(number_format((float)($row['hours'] ?? 0), 2)); ?> hrs</td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="3" style="text-align:center;">No records found for this month</td></tr>
    <?php endif; ?>
  </table>

  <div class="footer">&copy; <?php echo date("Y"); ?> Attendance System</div>
</div>

</body>
</html>

