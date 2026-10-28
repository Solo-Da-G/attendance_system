<?php
include(__DIR__ . "/includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Attendance List</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>
<?php include(__DIR__ . "/includes/sidebar.php"); ?>
<div class="content">
  <h2>Attendance List</h2>
  <div class="table-card" style="box-shadow:none; border:1px solid #e2e8f0; border-radius:16px; margin-top:16px; padding:0; background:#fff;">
    <div class="table-scroll">
      <table class="responsive-table" style="width:100%; margin:0; border-collapse:collapse;">
        <thead>
          <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
            <th>Staff ID</th>
            <th>Clock In</th>
            <th>Clock Out</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $res = $conn->query("SELECT * FROM attendance ORDER BY clock_in DESC LIMIT 200");
        if ($res && $res->num_rows > 0) {
          while ($row = $res->fetch_assoc()) {
            $statusClass = strtolower($row['status'] ?? 'in');
            $displayStatus = ($row['status'] ?? '') === 'missed_out' ? 'missed(clockout)' : ($row['status'] ?? 'in');
            $clockInFormatted = !empty($row['clock_in']) ? date("M d, Y h:i A", strtotime($row['clock_in'])) : '—';
            $clockOutFormatted = !empty($row['clock_out']) ? date("M d, Y h:i A", strtotime($row['clock_out'])) : '—';
            echo "<tr>
                    <td data-label='Staff ID'><code style='background:#f1f5f9;padding:2px 6px;border-radius:4px;'>{$row['staff_id']}</code></td>
                    <td data-label='Clock In'>{$clockInFormatted}</td>
                    <td data-label='Clock Out'>{$clockOutFormatted}</td>
                    <td data-label='Status' class='status {$statusClass}'>{$displayStatus}</td>
                  </tr>";
          }
        } else {
          echo "<tr><td colspan='4' style='text-align:center;padding:32px;color:#94a3b8;'>No attendance records found</td></tr>";
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="footer">
    &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon & Collins
  </div>
</div>
</body>
</html>
