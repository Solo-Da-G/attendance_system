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
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters. Please provide a positive radius in meters.']);
    exit;
}

// 1. Fetch branch name
$b_stmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ? LIMIT 1");
if (!$b_stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$b_stmt->bind_param("i", $branch_id);
$b_stmt->execute();
$b_res = $b_stmt->get_result();
$b_row = $b_res ? $b_res->fetch_assoc() : null;
$b_stmt->close();

if (!$b_row) {
    echo json_encode(['status' => 'error', 'message' => 'Branch not found.']);
    exit;
}

$branch_name = $b_row['branch_name'];

// 2. Update branches table
$stmt = $conn->prepare('UPDATE branches SET radius_meters = ? WHERE id = ?');
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database prepare error: ' . $conn->error]);
    exit;
}
$stmt->bind_param('ii', $radius, $branch_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update branch radius: ' . $conn->error]);
    exit;
}

// 3. Automatically synchronize assigned staff clock_radius so new meter applies immediately
$staff_updated = 0;
if (!empty($branch_name) && function_exists('db_has_column') && db_has_column($conn, 'staff', 'clock_radius')) {
    $s_sync = $conn->prepare("UPDATE staff SET clock_radius = ? WHERE LOWER(TRIM(branch)) = LOWER(TRIM(?))");
    if ($s_sync) {
        $s_sync->bind_param("is", $radius, $branch_name);
        $s_sync->execute();
        $staff_updated = $s_sync->affected_rows;
        $s_sync->close();
    }
}

echo json_encode([
    'status' => 'success',
    'message' => "Radius set to {$radius}m for " . htmlspecialchars($branch_name) . " (Auto-synced with {$staff_updated} staff members)",
    'radius' => $radius,
    'branch_id' => $branch_id,
    'branch_name' => $branch_name,
    'staff_synced' => $staff_updated
]);
?>