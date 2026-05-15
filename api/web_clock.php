<?php
/**
 * WEB CLOCK API (Geofenced + Facial Verification)
 */

include(__DIR__ . "/../includes/config.php");
include(__DIR__ . "/../lib/Geolocation.php");

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    echo json_encode(["status" => "error", "message" => "Session expired. Please log in again with your Staff ID."]);
    exit;
}

$staff_id       = $_SESSION['staff_id'] ?? null;
$lat            = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng            = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$photo          = $_POST['photo'] ?? null;
$action         = $_POST['action'] ?? '';
$face_verified  = ($_POST['face_verified'] ?? '') === '1';
$face_distance  = isset($_POST['face_distance']) ? (float)$_POST['face_distance'] : 999.0;
$gps_accuracy   = isset($_POST['gps_accuracy']) ? (float)$_POST['gps_accuracy'] : 0;
$face_threshold = 0.68;

if (!$staff_id) {
    echo json_encode([
        "status"  => "error",
        "message" => "Clock-in is for staff only. Log out and sign in with your Staff ID (not admin username).",
    ]);
    exit;
}

if ($lat === null || $lng === null) {
    echo json_encode(["status" => "error", "message" => "Location missing. Allow GPS for this site and try again."]);
    exit;
}

if (!$face_verified || $face_distance > $face_threshold) {
    echo json_encode([
        "status"  => "error",
        "message" => "Face did not match (score " . round($face_distance, 2) . "). Use the same lighting as your profile photo and look straight at the camera.",
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT photo, branch FROM staff WHERE staff_id = ? LIMIT 1");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$staff_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$photoStr = $staff_row['photo'] ?? '';
if (!$staff_row || strlen($photoStr) < 500 || !str_starts_with($photoStr, 'data:image')) {
    echo json_encode([
        "status"  => "error",
        "message" => "Profile photo missing or broken. Admin must re-upload your photo in Employees (clear face, JPG/PNG).",
    ]);
    exit;
}

// All branches — staff may clock at any registered location within radius
$branches = [];
$all = $conn->query("SELECT latitude, longitude, radius_meters, branch_name FROM branches");
if ($all) {
    while ($row = $all->fetch_assoc()) {
        $branches[] = $row;
    }
}

if (empty($branches)) {
    echo json_encode([
        "status"  => "error",
        "message" => "No branches configured. Admin must add a branch under Manage Branches.",
    ]);
    exit;
}

$accuracyBuffer = (int)min(max($gps_accuracy, 0), 150);
$geo = Geolocation::validateAgainstBranches($lat, $lng, $branches, $accuracyBuffer);

if (!$geo['allowed']) {
    $distances = Geolocation::distancesToBranches($lat, $lng, $branches);
    echo json_encode([
        "status"     => "error",
        "message"    => $geo['message'],
        "distances"  => $distances,
        "your_lat"   => $lat,
        "your_lng"   => $lng,
    ]);
    exit;
}

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
        echo json_encode([
            "status"  => "success",
            "message" => "Clocked in at " . ($geo['branch_name'] ?? 'branch') . "!",
        ]);
    } else {
        echo json_encode([
            "status"  => "error",
            "message" => "Could not save clock-in. " . ($conn->error ?: 'Database error.'),
        ]);
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
            echo json_encode(["status" => "success", "message" => "Clocked out successfully!"]);
        } else {
            echo json_encode([
                "status"  => "error",
                "message" => "Could not save clock-out. " . ($conn->error ?: 'Database error.'),
            ]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "No active session. Clock in first."]);
    }

} else {
    echo json_encode(["status" => "error", "message" => "Invalid action."]);
}
