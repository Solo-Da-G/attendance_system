<?php
/**
 * WEB CLOCK API (Geofenced + Facial Verification)
 */

include("../includes/config.php");
include("../lib/Geolocation.php");

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    echo json_encode(["status" => "error", "message" => "Session expired. Please log in again."]);
    exit;
}

$staff_id       = $_SESSION['staff_id'] ?? null;
$lat            = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng            = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$photo          = $_POST['photo'] ?? null;
$action         = $_POST['action'] ?? '';
$face_verified  = ($_POST['face_verified'] ?? '') === '1';
$face_distance  = isset($_POST['face_distance']) ? (float)$_POST['face_distance'] : 999.0;
$face_threshold = 0.6;

if (!$staff_id) {
    echo json_encode(["status" => "error", "message" => "Only staff accounts can clock in here."]);
    exit;
}

if ($lat === null || $lng === null) {
    echo json_encode(["status" => "error", "message" => "Location data missing. Enable GPS and refresh."]);
    exit;
}

if (!$face_verified || $face_distance > $face_threshold) {
    echo json_encode([
        "status"  => "error",
        "message" => "Face did not match your profile. Center your face in good lighting and try again.",
    ]);
    exit;
}

// Staff must have a profile photo on file
$stmt = $conn->prepare("SELECT photo, branch FROM staff WHERE staff_id = ? LIMIT 1");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$staff_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff_row || empty($staff_row['photo'])) {
    echo json_encode([
        "status"  => "error",
        "message" => "No profile photo on file. Ask your admin to upload your photo in Employees.",
    ]);
    exit;
}

// ---------------------------------------------------------------
// GEOFENCE VALIDATION
// ---------------------------------------------------------------
$branches = [];

// Prefer staff-assigned branch (case-insensitive name match)
$stmt = $conn->prepare(
    "SELECT b.latitude, b.longitude, b.radius_meters, b.branch_name
     FROM branches b
     INNER JOIN staff s ON LOWER(TRIM(s.branch)) = LOWER(TRIM(b.branch_name))
     WHERE s.staff_id = ?"
);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $branches[] = $row;
}
$stmt->close();

// If no assigned branch match, check all branches
if (empty($branches)) {
    $all = $conn->query("SELECT latitude, longitude, radius_meters, branch_name FROM branches");
    if ($all) {
        while ($row = $all->fetch_assoc()) {
            $branches[] = $row;
        }
    }
}

$geo = Geolocation::validateAgainstBranches($lat, $lng, $branches);
if (!$geo['allowed']) {
    echo json_encode(["status" => "error", "message" => $geo['message']]);
    exit;
}

// ---------------------------------------------------------------
// PROCESS CLOCKING
// ---------------------------------------------------------------
$now = date("Y-m-d H:i:s");

if ($action === 'clock_in') {
    $check = $conn->prepare("SELECT id FROM attendance WHERE staff_id = ? AND clock_out IS NULL LIMIT 1");
    $check->bind_param("s", $staff_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Already clocked in."]);
        $check->close();
        exit;
    }
    $check->close();

    $stmt = $conn->prepare(
        "INSERT INTO attendance (staff_id, clock_in, status, lat_in, lng_in, photo_in, is_geofenced)
         VALUES (?, ?, 'in', ?, ?, ?, 1)"
    );
    $stmt->bind_param("ssdds", $staff_id, $now, $lat, $lng, $photo);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Face verified & clocked in!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Could not save clock-in. Please try again."]);
    }
    $stmt->close();

} elseif ($action === 'clock_out') {
    $stmt = $conn->prepare(
        "SELECT id, clock_in FROM attendance WHERE staff_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1"
    );
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($record) {
        $total_hours = round((time() - strtotime($record['clock_in'])) / 3600, 2);
        $stmt = $conn->prepare(
            "UPDATE attendance SET clock_out = ?, status = 'out', total_hours = ?, lat_out = ?, lng_out = ?, photo_out = ? WHERE id = ?"
        );
        $stmt->bind_param("sdddsi", $now, $total_hours, $lat, $lng, $photo, $record['id']);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Face verified & clocked out!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Could not save clock-out. Please try again."]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "No active session found. Clock in first."]);
    }

} else {
    echo json_encode(["status" => "error", "message" => "Invalid clock action."]);
}
