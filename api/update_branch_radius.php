<?php
include(__DIR__ . '/includes/config.php');
header('Content-Type: application/json');
// Only admin/super_admin allowed
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}
$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
$radius = isset($_POST['radius_meters']) ? (int)$_POST['radius_meters'] : 0;
if ($branch_id <= 0 || $radius <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}
$stmt = $conn->prepare('UPDATE branches SET radius_meters = ? WHERE id = ?');
$stmt->bind_param('ii', $radius, $branch_id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Radius updated']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
$stmt->close();
?>