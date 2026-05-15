<?php
/**
 * Staff profile data for face clocking (avoids huge inline JSON on dashboard).
 */
include(__DIR__ . "/../includes/config.php");

header('Content-Type: application/json');

if (empty($_SESSION['staff_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Staff session required. Log in with your Staff ID, not an admin account.']);
    exit;
}

$staff_id = $_SESSION['staff_id'];

$stmt = $conn->prepare("SELECT staff_id, full_name, photo, branch FROM staff WHERE staff_id = ? LIMIT 1");
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

$branches = [];
$all = $conn->query("SELECT branch_name, latitude, longitude, radius_meters FROM branches");
if ($all) {
    while ($b = $all->fetch_assoc()) {
        $branches[] = $b;
    }
}

echo json_encode([
    'status'     => 'success',
    'staff_id'   => $row['staff_id'],
    'full_name'  => $row['full_name'],
    'branch'     => $row['branch'] ?? '',
    'photo'      => $photoOk ? $photo : '',
    'photo_ok'   => $photoOk,
    'photo_len'  => $photoLen,
    'branches'   => $branches,
]);
