<?php
/**
 * WEB CLOCK API (Geofenced)
 * 
 * Handles clock-in/out from the web/mobile dashboard with location verification.
 */

include("../includes/config.php");
include("../lib/Geolocation.php");

header('Content-Type: application/json');

if (!isset(<?php
/**
 * WEB CLOCK API (Geofenced)
 * 
 * Handles clock-in/out from the web/mobile dashboard with location verification.
 */

include("../includes/config.php");
include("../lib/Geolocation.php");

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$staff_id = $_SESSION['staff_id'] ?? null; // We need to ensure staff_id is in session
$lat      = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng      = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$action   = $_POST['action'] ?? ''; // 'clock_in' or 'clock_out'

if (!$staff_id) {
    echo json_encode(["status" => "error", "message" => "Staff ID not found in session."]);
    exit;
}

if ($lat === null || $lng === null) {
    echo json_encode(["status" => "error", "message" => "Location data missing. Please enable GPS."]);
    exit;
}

// ---------------------------------------------------------------
// GEOFENCE VALIDATION
// ---------------------------------------------------------------
// Get staff's branch or the default branch
$stmt = $conn->prepare("SELECT b.latitude, b.longitude, b.radius_meters FROM branches b 
                        JOIN staff s ON (s.branch = b.branch_name OR s.branch_id = b.id)
                        WHERE s.staff_id = ? LIMIT 1");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$branch) {
    // Fallback: If no branch assigned, check if there are ANY branches
    $res = $conn->query("SELECT latitude, longitude, radius_meters FROM branches LIMIT 1");
    $branch = $res->fetch_assoc();
}

if ($branch) {
    $is_near = Geolocation::isWithinRadius($lat, $lng, $branch['latitude'], $branch['longitude'], $branch['radius_meters']);
    if (!$is_near) {
        echo json_encode([
            "status" => "error", 
            "message" => "You are too far from the office to clock in/out.",
            "debug" => [
                "dist" => round(Geolocation::getDistance($lat, $lng, $branch['latitude'], $branch['longitude'])),
                "limit" => $branch['radius_meters']
            ]
        ]);
        exit;
    }
}

// ---------------------------------------------------------------
// PROCESS CLOCKING (Same logic as clock.php but for web)
// ---------------------------------------------------------------
$now  = date("Y-m-d H:i:s");
$date = date("Y-m-d");

if ($action === 'clock_in') {
    $stmt = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, status, lat_in, lng_in, is_geofenced) VALUES (?, ?, 'in', ?, ?, 1)");
    $stmt->bind_param("ssdd", $staff_id, $now, $lat, $lng);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Clocked in successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    }
    $stmt->close();

} else if ($action === 'clock_out') {
    // Find latest open clock-in for today
    $stmt = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ss", $staff_id, $date);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($record) {
        $clock_in_time = strtotime($record['clock_in']);
        $total_hours = round((time() - $clock_in_time) / 3600, 2);

        $stmt = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'out', total_hours = ?, lat_out = ?, lng_out = ? WHERE id = ?");
        $stmt->bind_param("sdddi", $now, $total_hours, $lat, $lng, $record['id']);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Clocked out successfully! Total: $total_hours hours"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "No active clock-in found for today."]);
    }
}

SESSION['admin_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$staff_id = $_SESSION['staff_id'] ?? null; // We need to ensure staff_id is in session
$lat      = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng      = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$action   = $_POST['action'] ?? ''; // 'clock_in' or 'clock_out'

if (!$staff_id) {
    echo json_encode(["status" => "error", "message" => "Staff ID not found in session."]);
    exit;
}

if ($lat === null || $lng === null) {
    echo json_encode(["status" => "error", "message" => "Location data missing. Please enable GPS."]);
    exit;
}

// ---------------------------------------------------------------
// GEOFENCE VALIDATION
// ---------------------------------------------------------------
// Get staff's branch or the default branch
$stmt = $conn->prepare("SELECT b.latitude, b.longitude, b.radius_meters FROM branches b 
                        JOIN staff s ON (s.branch = b.branch_name OR s.branch_id = b.id)
                        WHERE s.staff_id = ? LIMIT 1");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$branch) {
    // Fallback: If no branch assigned, check if there are ANY branches
    $res = $conn->query("SELECT latitude, longitude, radius_meters FROM branches LIMIT 1");
    $branch = $res->fetch_assoc();
}

if ($branch) {
    $is_near = Geolocation::isWithinRadius($lat, $lng, $branch['latitude'], $branch['longitude'], $branch['radius_meters']);
    if (!$is_near) {
        echo json_encode([
            "status" => "error", 
            "message" => "You are too far from the office to clock in/out.",
            "debug" => [
                "dist" => round(Geolocation::getDistance($lat, $lng, $branch['latitude'], $branch['longitude'])),
                "limit" => $branch['radius_meters']
            ]
        ]);
        exit;
    }
}

// ---------------------------------------------------------------
// PROCESS CLOCKING (Same logic as clock.php but for web)
// ---------------------------------------------------------------
$now  = date("Y-m-d H:i:s");
$date = date("Y-m-d");

if ($action === 'clock_in') {
    $stmt = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, status, lat_in, lng_in, is_geofenced) VALUES (?, ?, 'in', ?, ?, 1)");
    $stmt->bind_param("ssdd", $staff_id, $now, $lat, $lng);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Clocked in successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    }
    $stmt->close();

} else if ($action === 'clock_out') {
    // Find latest open clock-in for today
    $stmt = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ss", $staff_id, $date);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($record) {
        $clock_in_time = strtotime($record['clock_in']);
        $total_hours = round((time() - $clock_in_time) / 3600, 2);

        $stmt = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'out', total_hours = ?, lat_out = ?, lng_out = ? WHERE id = ?");
        $stmt->bind_param("sdddi", $now, $total_hours, $lat, $lng, $record['id']);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Clocked out successfully! Total: $total_hours hours"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "No active clock-in found for today."]);
    }
}


