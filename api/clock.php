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
$current_hour = (int)date('G');

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

// ---------------------------------------------------------------
// Stop clocking after 7:00pm
// ---------------------------------------------------------------
if ($current_hour >= 19) {
    echo json_encode(["status" => "error", "message" => "Clock-in and clock-out are disabled after 7:00 PM. The system will reset at 12:00 AM."]);
    exit;
}

// ---------------------------------------------------------------
// Universal GPS Branch Auto-Detection & Geofence Enforcement
// ---------------------------------------------------------------
$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : (isset($_POST['latitude']) ? (float)$_POST['latitude'] : null);
$lng = isset($_POST['lng']) ? (float)$_POST['lng'] : (isset($_POST['longitude']) ? (float)$_POST['longitude'] : null);

$geo = auto_detect_gps_branch($conn, $lat, $lng, $staff_id);
if (!$geo['success']) {
    echo json_encode(["status" => "error", "message" => $geo['error']]);
    exit;
}
$branch_name = $geo['branch_name'];

// Check today's attendance record
$stmt = $conn->prepare("SELECT id, clock_in, clock_out FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ss", $staff_id, $today);
$stmt->execute();
$check = $stmt->get_result();
$record = $check->fetch_assoc();
$stmt->close();

if (!$record) {
    // Clock in
    $stmt2 = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, status, source, lat_in, lng_in, is_geofenced, branch_in) VALUES (?, ?, 'in', 'web', ?, ?, 1, ?)");
    $stmt2->bind_param("ssdds", $staff_id, $current_time, $lat, $lng, $branch_name);
    $stmt2->execute();
    $stmt2->close();
    echo json_encode(["status" => "success", "message" => "Clock-in recorded at $current_time 📍 $branch_name"]);
} elseif ($record['clock_out'] === null) {
    // Clock out
    $clock_in_time = strtotime($record['clock_in']);
    $clock_out_time = strtotime($current_time);
    $diff_seconds = $clock_out_time - $clock_in_time;
    $total_hours = round($diff_seconds / 3600, 2);

    $stmt3 = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'out', total_hours = ?, lat_out = ?, lng_out = ?, branch_out = ? WHERE id = ?");
    $stmt3->bind_param("sdddsi", $current_time, $total_hours, $lat, $lng, $branch_name, $record['id']);
    $stmt3->execute();
    $stmt3->close();
    echo json_encode(["status" => "success", "message" => "Clock-out recorded at $current_time 📍 $branch_name. Total hours: $total_hours"]);
} else {
    // Already completed attendance for today
    echo json_encode(["status" => "error", "message" => "Already clocked in and out today. The system will reset at 12:00 AM."]);
}
?>
