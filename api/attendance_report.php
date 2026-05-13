<?php
session_start();
include(__DIR__ . "/../includes/config.php");

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
  <h2>Attendance Report</h2>
  <form method="GET">
    <label>From:</label> <input type="date" name="from" value="<?php echo isset($_GET['from']) ? htmlspecialchars($_GET['from']) : ''; ?>" required>
    <label>To:</label> <input type="date" name="to" value="<?php echo isset($_GET['to']) ? htmlspecialchars($_GET['to']) : ''; ?>" required>
    <button type="submit">Generate</button>
  </form>

  <?php
  if (isset($_GET['from']) && isset($_GET['to']) && !empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to = $_GET['to'];
    $stmt = $conn->prepare("SELECT * FROM attendance WHERE DATE(clock_in) BETWEEN ? AND ? ORDER BY clock_in DESC");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();

    echo "<table><tr><th>Staff ID</th><th>Clock In</th><th>Clock Out</th><th>Status</th><th>Total Hours</th></tr>";
    if ($res->num_rows > 0) {
      while ($row = $res->fetch_assoc()) {
        echo "<tr>
          <td>{$row['staff_id']}</td>
          <td>{$row['clock_in']}</td>
          <td>" . ($row['clock_out'] ?? '—') . "</td>
          <td class='status " . strtolower($row['status']) . "'>{$row['status']}</td>
          <td>" . ($row['total_hours'] ?? '0') . " hrs</td>
        </tr>";
      }
    } else {
      echo "<tr><td colspan='5' style='text-align:center;'>No records found</td></tr>";
    }
    echo "</table>";
    $stmt->close();
  }
  ?>

  <div class="footer">
    &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Collins
  </div>
</div>
</body>
</html>
