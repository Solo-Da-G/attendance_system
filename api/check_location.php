<?php
include(__DIR__ . "/includes/config.php");
include(__DIR__ . "/lib/Geolocation.php");

header('Content-Type: application/json');

if (empty($_SESSION['staff_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Staff login required.']);
    exit;
}

$staff_id = $_SESSION['staff_id'];
$lat      = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng      = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

if ($lat === null || $lng === null) {
    echo json_encode(['status' => 'error', 'message' => 'GPS coordinates missing.']);
    exit;
}

$is_near = false;
$nearest_branch = null;

if (function_exists('db_has_table') && db_has_table($conn, 'branches')) {
    $res = $conn->query("SELECT id, branch_name, latitude, longitude, radius_meters FROM branches");
    if ($res) {
        while ($b = $res->fetch_assoc()) {
            if ($b['latitude'] !== null && $b['longitude'] !== null) {
                $dist = Geolocation::getDistance($lat, $lng, $b['latitude'], $b['longitude']);
                if ($dist <= ($b['radius_meters'] ?? 200)) {
                    $is_near = true;
                    $nearest_branch = $b['branch_name'];
                    break;
                }
            }
        }
    }
}

if ($is_near) {
    // Auto-register staff location to match current GPS with branch's configured radius
    $radius = 200;
    if ($nearest_branch) {
        $br = $conn->prepare("SELECT radius_meters FROM branches WHERE LOWER(TRIM(branch_name)) = LOWER(TRIM(?)) LIMIT 1");
        if ($br) {
            $br->bind_param("s", $nearest_branch);
            $br->execute();
            $br_row = $br->get_result()->fetch_assoc();
            $br->close();
            if (!empty($br_row['radius_meters'])) {
                $radius = (int)$br_row['radius_meters'];
            }
        }
    }
    if (function_exists('db_has_column') && db_has_column($conn, 'staff', 'clock_lat')) {
        $stmt = $conn->prepare("UPDATE staff SET clock_lat = ?, clock_lng = ?, clock_radius = ? WHERE staff_id = ?");
        if ($stmt) {
            $stmt->bind_param("ddis", $lat, $lng, $radius, $staff_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Location automatically detected.',
        'branch' => $nearest_branch
    ]);
} else {
    echo json_encode([
        'status' => 'not_near',
        'message' => 'Not near any registered branch.'
    ]);
}
