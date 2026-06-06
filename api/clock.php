<?php
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$staff_id = $_POST['staff_id'] ?? '';
date_default_timezone_set("Africa/Lagos");
$current_time = date("Y-m-d H:i:s");
$today = date("Y-m-d");

// Validate staff exists
$stmt = $conn->prepare("SELECT id FROM staff WHERE staff_id = ?");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$staff = $stmt->get_result();

if ($staff->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Staff not found"]);
    exit;
}
$stmt->close();

// ---------------------------------------------------------------
// Auto-close missed clock-outs (previous day) at 12:00am
// ---------------------------------------------------------------
$stmtMiss = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
if ($stmtMiss) {
    $stmtMiss->bind_param("s", $staff_id);
    $stmtMiss->execute();
    $miss = $stmtMiss->get_result()->fetch_assoc();
    $stmtMiss->close();
    if ($miss) {
        $clock_in_date = date("Y-m-d", strtotime($miss['clock_in']));
        if ($clock_in_date < $today) {
            $midnight = date("Y-m-d 00:00:00", strtotime($clock_in_date . " +1 day"));
            $upd = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'missed_out', total_hours = 0 WHERE id = ? AND clock_out IS NULL");
            if ($upd) {
                $upd->bind_param("si", $midnight, $miss['id']);
                $upd->execute();
                $upd->close();
            }
        }
    }
}

// Check if already clocked in today
$stmt = $conn->prepare("SELECT id, clock_in, clock_out FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ss", $staff_id, $today);
$stmt->execute();
$check = $stmt->get_result();
$record = $check->fetch_assoc();
$stmt->close();

if (!$record || $record['clock_out'] !== null) {
    // Clock in
    $stmt2 = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, status) VALUES (?, ?, 'in')");
    $stmt2->bind_param("ss", $staff_id, $current_time);
    $stmt2->execute();
    $stmt2->close();
    echo json_encode(["status" => "success", "message" => "Clock-in recorded at $current_time"]);
} else {
    // Clock out
    $clock_in_time = strtotime($record['clock_in']);
    $clock_out_time = strtotime($current_time);
    $diff_seconds = $clock_out_time - $clock_in_time;
    $total_hours = round($diff_seconds / 3600, 2);

    $stmt3 = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'out', total_hours = ? WHERE id = ?");
    $stmt3->bind_param("sdi", $current_time, $total_hours, $record['id']);
    $stmt3->execute();
    $stmt3->close();
    echo json_encode(["status" => "success", "message" => "Clock-out recorded at $current_time. Total hours: $total_hours"]);
}
?>
