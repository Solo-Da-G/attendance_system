<?php
include(__DIR__ . "/../includes/config.php");

if (empty($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

$staff_id = $_SESSION['staff_id'];
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';

$rows = [];
if ($from !== '' && $to !== '') {
    $stmt = $conn->prepare("SELECT clock_in, clock_out, status, total_hours FROM attendance WHERE staff_id = ? AND DATE(clock_in) BETWEEN ? AND ? ORDER BY clock_in DESC");
    $stmt->bind_param("sss", $staff_id, $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT clock_in, clock_out, status, total_hours FROM attendance WHERE staff_id = ? ORDER BY clock_in DESC LIMIT 200");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Attendance</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<div class="content">
  <h2>My Attendance</h2>
  <p class="subtitle">Your personal attendance history</p>

  <form method="GET">
    <label>From:</label>
    <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
    <label>To:</label>
    <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
    <button type="submit">Filter</button>
    <a href="my_attendance.php" style="margin-left:10px; color:var(--primary); font-weight:600;">Reset</a>
    <button type="button" class="print-btn" onclick="window.print()">🖨️ Print</button>
  </form>

  <table>
    <tr>
      <th>Date</th>
      <th>Time In</th>
      <th>Time Out</th>
      <th>Status</th>
      <th>Total Hours</th>
    </tr>
    <?php if (!empty($rows)): ?>
      <?php foreach ($rows as $row): ?>
        <?php
          $date = date("Y-m-d", strtotime($row['clock_in']));
          $timeIn = date("H:i:s", strtotime($row['clock_in']));
          $timeOut = $row['clock_out'] ? date("H:i:s", strtotime($row['clock_out'])) : "—";
          $statusClass = strtolower($row['status'] ?? 'in');
          $hrs = isset($row['total_hours']) ? (float)$row['total_hours'] : 0;
        ?>
        <tr>
          <td><?php echo htmlspecialchars($date); ?></td>
          <td><?php echo htmlspecialchars($timeIn); ?></td>
          <td><?php echo htmlspecialchars($timeOut); ?></td>
          <td class="status <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($row['status'] ?? 'in'); ?></td>
          <td><?php echo htmlspecialchars(number_format($hrs, 2)); ?> hrs</td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="5" style="text-align:center;">No attendance records found</td></tr>
    <?php endif; ?>
  </table>

  <div class="footer">&copy; <?php echo date("Y"); ?> Attendance System</div>
</div>

</body>
</html>

