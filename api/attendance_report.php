<?php
include(__DIR__ . "/includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

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
    $stmt = $conn->prepare("SELECT a.* FROM attendance a JOIN staff s ON a.staff_id = s.staff_id WHERE s.deleted_at IS NULL AND DATE(a.clock_in) BETWEEN ? AND ? ORDER BY a.clock_in DESC");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();

    echo "<div class='table-card' style='box-shadow:none;border:1px solid #e2e8f0;border-radius:16px;margin-top:16px;padding:0;overflow:hidden;background:#fff;'>
            <div class='table-scroll' style='width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;'>
              <table class='responsive-table' style='width:100%;min-width:600px;margin:0;border-collapse:collapse;'>
                <thead>
                  <tr style='background:#f8fafc;border-bottom:2px solid #e2e8f0;'>
                    <th style='padding:12px 16px;font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;'>Staff ID</th>
                    <th style='padding:12px 16px;font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;'>Clock In</th>
                    <th style='padding:12px 16px;font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;'>Clock Out</th>
                    <th style='padding:12px 16px;font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;'>Status</th>
                    <th style='padding:12px 16px;font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;'>Total Hours</th>
                  </tr>
                </thead>
                <tbody>";
    if ($res->num_rows > 0) {
      while ($row = $res->fetch_assoc()) {
        $hours = floatval($row['total_hours']);
        $displayStatus = $row['status'] === 'missed_out' ? 'missed(clockout)' : $row['status'];
        echo "<tr>
          <td data-label='Staff ID' style='padding:12px 16px;'><code style='background:#f1f5f9;padding:2px 6px;border-radius:4px;'>{$row['staff_id']}</code></td>
          <td data-label='Clock In' style='padding:12px 16px;'>{$row['clock_in']}</td>
          <td data-label='Clock Out' style='padding:12px 16px;'>" . ($row['clock_out'] ?? '—') . "</td>
          <td data-label='Status' style='padding:12px 16px;' class='status " . strtolower($row['status']) . "'>{$displayStatus}</td>
          <td data-label='Total Hours' style='padding:12px 16px;'><strong>" . formatHours($hours) . "</strong></td>
        </tr>";
      }
    } else {
      echo "<tr><td colspan='5' style='text-align:center;padding:32px;color:#94a3b8;'>No records found for selected dates</td></tr>";
    }
    echo "      </tbody>
              </table>
            </div>
          </div>";
    $stmt->close();
  }
  ?>

  <div class="footer">
    &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Collins
  </div>
</div>
</body>
</html>


