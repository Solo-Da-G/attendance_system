<?php
include(__DIR__ . "/../includes/config.php");

header('Content-Type: application/json');
http_response_code(410);
echo json_encode([
  "status" => "error",
  "message" => "This endpoint is disabled. Use the face + GPS clocking on the dashboard (web_clock.php) or device push API.",
]);
exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $staff_id = $_POST['staff_id'] ?? '';
  date_default_timezone_set("Africa/Lagos");
  $current_time = date("Y-m-d H:i:s");
  $today = date("Y-m-d");

  // Validate staff exists (prepared statement)
  $stmt = $conn->prepare("SELECT id FROM staff WHERE staff_id = ?");
  $stmt->bind_param("s", $staff_id);
  $stmt->execute();
  $staff = $stmt->get_result();

  if ($staff->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Staff not found"]);
    exit;
  }
  $stmt->close();

  // Check if already clocked in today (prepared statement)
  $stmt = $conn->prepare("SELECT id, clock_in, clock_out FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? ORDER BY id DESC LIMIT 1");
  $stmt->bind_param("ss", $staff_id, $today);
  $stmt->execute();
  $check = $stmt->get_result();

  if ($check->num_rows == 0 || $check->fetch_assoc()['clock_out'] !== null) {
    // No record today OR last record already clocked out — create new clock-in
    $stmt2 = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, status) VALUES (?, ?, 'in')");
    $stmt2->bind_param("ss", $staff_id, $current_time);
    $stmt2->execute();
    $stmt2->close();
    echo json_encode(["status" => "success", "message" => "Clock-in recorded at $current_time"]);
  } else {
    // Clock out — calculate total_hours
    // Re-fetch the record to get clock_in time
    $stmt2 = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
    $stmt2->bind_param("ss", $staff_id, $today);
    $stmt2->execute();
    $row = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    if ($row) {
      // Calculate total hours
      $clock_in_time = strtotime($row['clock_in']);
      $clock_out_time = strtotime($current_time);
      $diff_seconds = $clock_out_time - $clock_in_time;
      $total_hours = round($diff_seconds / 3600, 2);

      $stmt3 = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'out', total_hours = ? WHERE id = ?");
      $stmt3->bind_param("sdi", $current_time, $total_hours, $row['id']);
      $stmt3->execute();
      $stmt3->close();
      echo json_encode(["status" => "success", "message" => "Clock-out recorded at $current_time. Total hours: $total_hours"]);
    } else {
      echo json_encode(["status" => "error", "message" => "No open clock-in found"]);
    }
  }
  $stmt->close();
}
?>


