<?php
session_start();
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
<title>Attendance Report</title>
<link rel="stylesheet" href="asset/css/style.css">
</head>
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<div class="content">
  <h2>📊 Attendance Report</h2>

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
      <th>Status</th>
      <th>Total Hours</th>
    </tr>

    <?php
    $totalRecords = 0;
    $totalHoursAll = 0;

    if (!empty($_GET['filter_date'])) {
        $filter_date = $_GET['filter_date'];
        $stmt = $conn->prepare("
          SELECT a.id, a.staff_id, s.full_name, s.department, a.clock_in, a.clock_out, a.status, a.total_hours
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
          SELECT a.id, a.staff_id, s.full_name, s.department, a.clock_in, a.clock_out, a.status, a.total_hours
          FROM attendance a
          JOIN staff s ON a.staff_id = s.staff_id
          ORDER BY a.clock_in DESC
        ");
    }

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $statusClass = strtolower($row['status']);
            $hours = floatval($row['total_hours']);
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['staff_id']}</td>
                    <td>" . htmlspecialchars($row['full_name']) . "</td>
                    <td>{$row['department']}</td>
                    <td>{$row['clock_in']}</td>
                    <td>" . ($row['clock_out'] ?? '—') . "</td>
                    <td class='status {$statusClass}'>{$row['status']}</td>
                    <td>{$hours} hrs</td>
                  </tr>";
            $totalRecords++;
            $totalHoursAll += $hours;
        }
    } else {
        echo "<tr><td colspan='8' style='text-align:center;'>No attendance records found.</td></tr>";
    }

    if (isset($stmt)) $stmt->close();
    ?>
  </table>

  <div class="summary">
    <p>Total Records: <?php echo $totalRecords; ?></p>
    <p>Total Hours (All Staff): <?php echo round($totalHoursAll, 2); ?> hrs</p>
  </div>
</div>

</body>
</html>
