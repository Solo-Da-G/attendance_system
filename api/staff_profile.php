<?php
/**
 * Staff profile data for face clocking (avoids huge inline JSON on dashboard).
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

if (empty($_SESSION['staff_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Staff session required. Log in with your Staff ID, not an admin account.']);
    exit;
}

$staff_id = $_SESSION['staff_id'];

$selectClockLat = function_exists('db_has_column') && db_has_column($conn, 'staff', 'clock_lat') ? 'clock_lat' : 'NULL AS clock_lat';
$selectClockLng = function_exists('db_has_column') && db_has_column($conn, 'staff', 'clock_lng') ? 'clock_lng' : 'NULL AS clock_lng';
$selectClockRadius = function_exists('db_has_column') && db_has_column($conn, 'staff', 'clock_radius') ? 'clock_radius' : '300 AS clock_radius';

$stmt = $conn->prepare(
    "SELECT staff_id, full_name, photo, branch, {$selectClockLat}, {$selectClockLng}, {$selectClockRadius} FROM staff WHERE staff_id = ? LIMIT 1"
);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Staff record not found.']);
    exit;
}

$photo = $row['photo'] ?? '';
$photoLen = strlen($photo);
$photoOk = $photoLen > 500 && str_starts_with($photo, 'data:image');

// If photo is invalid, provide clear error message
if (!$photoOk) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Your profile photo is missing or invalid. Please ask admin to upload a clear photo of your face.',
        'photo_ok' => false,
        'photo_len' => $photoLen
    ]);
    exit;
}

$branches = [];
if (function_exists('db_has_table') && db_has_table($conn, 'branches')) {
    $all = $conn->query("SELECT branch_name, latitude, longitude, radius_meters FROM branches");
    if ($all) {
        while ($b = $all->fetch_assoc()) {
            $branches[] = $b;
        }
    }
}

echo json_encode([
    'status'        => 'success',
    'staff_id'      => $row['staff_id'],
    'full_name'     => $row['full_name'],
    'branch'        => $row['branch'] ?? '',
    'photo'         => $photo,
    'photo_ok'      => $photoOk,
    'photo_len'     => $photoLen,
    'branches'      => $branches,
    'clock_lat'     => $row['clock_lat'] ?? null,
    'clock_lng'     => $row['clock_lng'] ?? null,
    'clock_radius'  => (int)($row['clock_radius'] ?? 300),
    'location_set'  => !empty($row['clock_lat']) && !empty($row['clock_lng']),
]);
?>
