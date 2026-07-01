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
$stat_open = 0;

$r = $conn->query("SELECT COUNT(*) AS c FROM attendance");
if ($r && !is_bool($r)) $stat_total = (int)($r->fetch_assoc()['c'] ?? 0);

$stmtStat = $conn->prepare("SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in) = ?");
if ($stmtStat) {
  $stmtStat->bind_param("s", $today);
  $stmtStat->execute();
  $resStat = $stmtStat->get_result();
  if ($resStat) $stat_today = (int)($resStat->fetch_assoc()['c'] ?? 0);
  $stmtStat->close();
}

$r2 = $conn->query("SELECT COUNT(*) AS c FROM attendance WHERE clock_out IS NULL");
if ($r2 && !is_bool($r2)) $stat_open = (int)($r2->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance Records</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<!-- Main Content -->
<div class="content">
  <div style="background: linear-gradient(135deg, #0f172a, #1e293b); color:white; padding:32px; border-radius:24px; margin-bottom:22px;">
    <h2 style="margin:0; font-size:28px;">🕒 Attendance Records</h2>
    <p style="opacity:0.75; margin-top:10px;">All staff clock-ins and clock-outs (mobile geofencing + device fingerprint)</p>
  </div>

  <div class="cards" style="margin-top:0; margin-bottom:18px;">
    <div class="card"><h3>Total Records</h3><p><?php echo (int)$stat_total; ?></p></div>
    <div class="card"><h3>Today</h3><p><?php echo (int)$stat_today; ?></p></div>
    <div class="card"><h3>Currently In</h3><p><?php echo (int)$stat_open; ?></p></div>
  </div>

  <?php
  // Fetch branches for dropdown
  $branch_res = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name ASC");
  $branches = [];
  if ($branch_res) {
      while ($b = $branch_res->fetch_assoc()) {
          $branches[] = $b['branch_name'];
      }
  }
  $selected_branch = $_GET['filter_branch'] ?? '';
  ?>
  <!-- Filter form -->
  <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
    <label>From:</label>
    <input type="date" name="from" value="<?php echo isset($_GET['from']) ? htmlspecialchars($_GET['from']) : ''; ?>" required>
    <label>To:</label>
    <input type="date" name="to" value="<?php echo isset($_GET['to']) ? htmlspecialchars($_GET['to']) : ''; ?>" required>
    <label>Branch:</label>
    <select name="filter_branch" style="padding: 8px; border-radius: 8px; border: 1px solid #cbd5e1;">
        <option value="">All Branches</option>
        <?php foreach ($branches as $br): ?>
            <option value="<?php echo htmlspecialchars($br); ?>" <?php echo $selected_branch === $br ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($br); ?>
            </option>
        <?php endforeach; ?>
    </select>
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
      <th>Source</th>
      <th>Selfie</th>
      <th>Status</th>
    </tr>

    <?php
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    $filter_branch = $_GET['filter_branch'] ?? '';

    $query = "SELECT s.full_name, s.staff_id, s.job_title, s.branch, a.clock_in, a.clock_out, a.status, a.source, a.photo_in, a.photo_out, a.branch_in, a.branch_out
              FROM attendance a
              JOIN staff s ON a.staff_id = s.staff_id WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($from) && !empty($to)) {
        $query .= " AND DATE(a.clock_in) BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
        $types .= "ss";
    }

    if (!empty($filter_branch)) {
        $query .= " AND (a.branch_in = ? OR a.branch_out = ?)";
        $params[] = $filter_branch;
        $params[] = $filter_branch;
        $types .= "ss";
    }

    $query .= " ORDER BY a.clock_in DESC";

    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }

    if ($result && $result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
        $date = date("Y-m-d", strtotime($row['clock_in']));
        $timeIn = date("H:i:s", strtotime($row['clock_in']));
        $timeOut = $row['clock_out'] ? date("H:i:s", strtotime($row['clock_out'])) : "—";
        $statusClass = strtolower($row['status'] ?? 'in');
        $src = strtolower(trim((string)($row['source'] ?? '')));
        $srcLabel = $src === 'device' ? 'DEVICE' : ($src === 'mobile' ? 'MOBILE' : 'UNKNOWN');
        $srcBadge = $src === 'device' ? 'badge-info' : ($src === 'mobile' ? 'badge-success' : 'badge-warning');
        $selfie = $row['photo_in'] ?: ($row['photo_out'] ?: '');

        $b_in = htmlspecialchars($row['branch_in'] ?? 'Unknown Branch');
        $b_out = htmlspecialchars($row['branch_out'] ?? 'Unknown Branch');
        $timeInHtml = "{$timeIn}<br><small style='color:#4f46e5;font-weight:600;'>📍 {$b_in}</small>";
        $timeOutHtml = "—";
        if ($row['clock_out']) {
            $timeOutHtml = "{$timeOut}<br><small style='color:#4f46e5;font-weight:600;'>📍 {$b_out}</small>";
        }

        echo "<tr>
          <td>" . htmlspecialchars($row['full_name']) . "</td>
          <td>{$row['staff_id']}</td>
          <td>{$row['job_title']}</td>
          <td>{$row['branch']}</td>
          <td>{$date}</td>
          <td>{$timeInHtml}</td>
          <td>{$timeOutHtml}</td>
          <td><span class='badge {$srcBadge}'>{$srcLabel}</span></td>
          <td>" . ($selfie ? "<img src='{$selfie}' class='photo' style='border-radius:12px;' alt='Selfie'>" : "<span style='color:var(--text-muted);'>—</span>") . "</td>
          <td class='status {$statusClass}'>" . ($row['status'] ?? 'In') . "</td>
        </tr>";
      }
    } else {
      echo "<tr><td colspan='10' style='text-align:center;'>No attendance records found</td></tr>";
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
      try { $stmt->close(); } catch (Throwable $e) {}
    }
    ?>
  </table>

  <div class="footer">
    &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Mbewu
  </div>
</div>

</body>
</html>
