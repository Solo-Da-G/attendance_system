<?php
/**
 * FACE VERIFICATION API
 * 
 * Returns the profile photo URL for the logged-in staff member.
 * Used by the face verification JavaScript to compare webcam capture
 * against the stored profile photo.
 */
session_start();
include("../includes/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$staff_id = $_SESSION['staff_id'] ?? null;

if (!$staff_id) {
    echo json_encode(["status" => "error", "message" => "Staff ID not found in session."]);
    exit;
}

// Fetch staff photo
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

if (empty($staff['photo'])) {
    echo json_encode([
        "status" => "error", 
        "message" => "No profile photo found. Please contact your admin to upload your photo before clocking in."
    ]);
    exit;
}

// Check if photo file actually exists
$photo_path = "../uploads/" . $staff['photo'];
if (!file_exists($photo_path)) {
    echo json_encode([
        "status" => "error", 
        "message" => "Profile photo file is missing. Please contact your admin to re-upload your photo."
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "photo_url" => "uploads/" . $staff['photo'],
    "staff_name" => $staff['full_name']
]);
?>
