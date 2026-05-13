<?php

include(__DIR__ . "/../includes/config.php");

// Redirect if not logged in
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance Records</title>
<link rel="stylesheet" href="asset/css/style.css">
</head>
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<!-- Main Content -->
<div class="content">
  <h2>Attendance Records</h2>

  <!-- Filter form -->
  <form method="GET">
    <label>From:</label>
    <input type="date" name="from" value="<?php echo isset($_GET['from']) ? htmlspecialchars($_GET['from']) : ''; ?>" required>
    <label>To:</label>
    <input type="date" name="to" value="<?php echo isset($_GET['to']) ? htmlspecialchars($_GET['to']) : ''; ?>" required>
    <button type="submit">Filter</button>
    <button type="button" class="print-btn" onclick="window.print()">🖨️ Print</button>
  </form>

  <table>
    <tr>
      <th>Employee Name</th>
      <th>Employee ID</th>
      <th>Job Title</th>
      <th>Branch</th>
      <th>Date</th>
      <th>Time In</th>
      <th>Time Out</th>
      <th>Status</th>
    </tr>

    <?php
    // Build query with prepared statements for date filter
    if (isset($_GET['from']) && isset($_GET['to']) && !empty($_GET['from']) && !empty($_GET['to'])) {
      $from = $_GET['from'];
      $to = $_GET['to'];
      $stmt = $conn->prepare("
        SELECT s.full_name, s.staff_id, s.job_title, s.branch, a.clock_in, a.clock_out, a.status 
        FROM attendance a
        JOIN staff s ON a.staff_id = s.staff_id
        WHERE DATE(a.clock_in) BETWEEN ? AND ?
        ORDER BY a.clock_in DESC
      ");
      $stmt->bind_param("ss", $from, $to);
      $stmt->execute();
      $result = $stmt->get_result();
    } else {
      $result = $conn->query("
        SELECT s.full_name, s.staff_id, s.job_title, s.branch, a.clock_in, a.clock_out, a.status 
        FROM attendance a
        JOIN staff s ON a.staff_id = s.staff_id
        ORDER BY a.clock_in DESC
      ");
    }

    if ($result && $result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
        $date = date("Y-m-d", strtotime($row['clock_in']));
        $timeIn = date("H:i:s", strtotime($row['clock_in']));
        $timeOut = $row['clock_out'] ? date("H:i:s", strtotime($row['clock_out'])) : "—";
        $statusClass = strtolower($row['status']);

        echo "<tr>
          <td>" . htmlspecialchars($row['full_name']) . "</td>
          <td>{$row['staff_id']}</td>
          <td>{$row['job_title']}</td>
          <td>{$row['branch']}</td>
          <td>{$date}</td>
          <td>{$timeIn}</td>
          <td>{$timeOut}</td>
          <td class='status {$statusClass}'>{$row['status']}</td>
        </tr>";
      }
    } else {
      echo "<tr><td colspan='8' style='text-align:center;'>No attendance records found</td></tr>";
    }

    if (isset($stmt)) $stmt->close();
    ?>
  </table>

  <div class="footer">
    &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Mbewu
  </div>
</div>

</body>
</html>
