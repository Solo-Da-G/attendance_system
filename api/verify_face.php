<?php
/**
 * FACE VERIFICATION API
 * 
 * Returns the profile photo URL for the logged-in staff member.
 * Used by the face verification JavaScript to compare webcam capture
 * against the stored profile photo.
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

// This endpoint is kept for backward compatibility.
// The system now stores staff photos as base64 data URIs in the database.

if (empty($_SESSION['staff_id'])) {
    echo json_encode(["status" => "error", "message" => "Staff session required."]);
    exit;
}

$staff_id = $_SESSION['staff_id'];

$stmt = $conn->prepare("SELECT photo, full_name FROM staff WHERE staff_id = ? LIMIT 1");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();
$stmt->close();

if (!$staff) {
    echo json_encode(["status" => "error", "message" => "Staff record not found."]);
    exit;
}

$photo = $staff['photo'] ?? '';
$ok = is_string($photo) && strlen($photo) > 500 && str_starts_with($photo, 'data:image');

if (!$ok) {
    echo json_encode([
        "status" => "error",
        "message" => "Profile photo missing or invalid. Admin should re-upload a clear JPG/PNG in Employees."
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "photo" => $photo,
    "staff_name" => $staff['full_name']
]);
?>
