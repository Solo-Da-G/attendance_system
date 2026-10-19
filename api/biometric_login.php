<?php
/**
 * WEBAUTHN BIOMETRIC LOGIN API
 * Authenticates user via registered platform fingerprint sensor / Passkey
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: $_POST;

$credential_id = trim($data['credential_id'] ?? '');

if (empty($credential_id)) {
    echo json_encode(["status" => "error", "message" => "No credential ID received from biometric sensor."]);
    exit;
}

// 1. Look up credential in staff_biometrics
$stmt = $conn->prepare("
    SELECT b.staff_id, b.device_name, s.full_name, s.status, s.branch_id 
    FROM staff_biometrics b
    JOIN staff s ON b.staff_id = s.staff_id
    WHERE b.credential_id = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database query error."]);
    exit;
}

$stmt->bind_param("s", $credential_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode([
        "status" => "error",
        "message" => "Unrecognized fingerprint credential. Please sign in with your password and register your device in the dashboard."
    ]);
    exit;
}

if (isset($user['status']) && $user['status'] === 'inactive') {
    echo json_encode(["status" => "error", "message" => "Your staff account has been deactivated. Contact an administrator."]);
    exit;
}

// 2. Establish authenticated session
session_regenerate_id(true);
$_SESSION['staff_id'] = $user['staff_id'];
$_SESSION['staff_name'] = $user['full_name'];
$_SESSION['branch_id'] = $user['branch_id'];
$_SESSION['login_time'] = time();

echo json_encode([
    "status" => "success",
    "message" => "Fingerprint verified! Welcome, " . $user['full_name'] . ".",
    "redirect" => "dashboard.php"
]);
exit;
