<?php
/**
 * WEB CLOCK API (Geofenced + Facial Capture)
 */

include("../includes/config.php");
include("../lib/Geolocation.php");

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$staff_id = $_SESSION['staff_id'] ?? null; 
$lat      = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng      = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$photo    = $_POST['photo'] ?? null; // Base64 selfie
$action   = $_POST['action'] ?? '';

if (!$staff_id) {
    echo json_encode(["status" => "error", "message" => "Staff ID missing from session."]);
    exit;
}

if ($lat === null || $lng === null) {
    echo json_encode(["status" => "error", "message" => "Location data missing."]);
    exit;
}

// ---------------------------------------------------------------
// GEOFENCE VALIDATION
// ---------------------------------------------------------------
$stmt = $conn->prepare("SELECT b.latitude, b.longitude, b.radius_meters FROM branches b 
                        JOIN staff s ON (s.branch = b.branch_name)
                        WHERE s.staff_id = ? LIMIT 1");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$branch) {
    $res = $conn->query("SELECT latitude, longitude, radius_meters FROM branches LIMIT 1");
    $branch = $res->fetch_assoc();
}

if ($branch) {
    if (!Geolocation::isWithinRadius($lat, $lng, $branch['latitude'], $branch['longitude'], $branch['radius_meters'])) {
        echo json_encode(["status" => "error", "message" => "You are outside the allowed branch radius."]);
        exit;
    }
}

// ---------------------------------------------------------------
// PROCESS CLOCKING
// ---------------------------------------------------------------
$now  = date("Y-m-d H:i:s");

if ($action === 'clock_in') {
    // Check for open session
    $check = $conn->prepare("SELECT id FROM attendance WHERE staff_id = ? AND clock_out IS NULL LIMIT 1");
    $check->bind_param("s", $staff_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Already clocked in."]);
        exit;
    }
    $check->close();

    $stmt = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, status, lat_in, lng_in, photo_in, is_geofenced) VALUES (?, ?, 'in', ?, ?, ?, 1)");
    $stmt->bind_param("ssdds", $staff_id, $now, $lat, $lng, $photo);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Verified & Clocked in!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "DB Error"]);
    }
    $stmt->close();

} else if ($action === 'clock_out') {
    $stmt = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($record) {
        $total_hours = round((time() - strtotime($record['clock_in'])) / 3600, 2);
        $stmt = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'out', total_hours = ?, lat_out = ?, lng_out = ?, photo_out = ? WHERE id = ?");
        $stmt->bind_param("sdddsi", $now, $total_hours, $lat, $lng, $photo, $record['id']);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Verified & Clocked out!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "DB Error"]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "No active session found."]);
    }
}
