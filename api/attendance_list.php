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
<title>Attendance List</title>
<link rel="stylesheet" href="asset/css/style.css">
</head>
<body>
<?php include("includes/sidebar.php"); ?>
<div class="content">
  <h2>Attendance List</h2>
  <table>
    <tr><th>Staff ID</th><th>Clock In</th><th>Clock Out</th><th>Status</th></tr>
    <?php
    $res = $conn->query("SELECT * FROM attendance ORDER BY clock_in DESC");
    if ($res && $res->num_rows > 0) {
      while ($row = $res->fetch_assoc()) {
        $statusClass = strtolower($row['status']);
        echo "<tr>
                <td>{$row['staff_id']}</td>
                <td>{$row['clock_in']}</td>
                <td>" . ($row['clock_out'] ?? '—') . "</td>
                <td class='status {$statusClass}'>{$row['status']}</td>
              </tr>";
      }
    } else {
      echo "<tr><td colspan='4' style='text-align:center;'>No attendance records found</td></tr>";
    }
    ?>
  </table>

  <div class="footer">
    &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon & Collins
  </div>
</div>
</body>
</html>
