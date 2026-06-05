<?php
/**
 * Admin: update branch lat/lng from device GPS (Manage Branches).
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Admin access required.']);
    exit;
}

$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
$lat       = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng       = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

if ($branch_id < 1 || $lat === null || $lng === null) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid branch or coordinates.']);
    exit;
}

$stmt = $conn->prepare("UPDATE branches SET latitude = ?, longitude = ? WHERE id = ?");
$stmt->bind_param("ddi", $lat, $lng, $branch_id);

if ($stmt->execute()) {
    echo json_encode([
        'status'  => 'success',
        'message' => 'Branch location updated to your device GPS.',
        'lat'     => $lat,
        'lng'     => $lng,
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed.']);
}
$stmt->close();
