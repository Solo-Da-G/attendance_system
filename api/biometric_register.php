<?php
/**
 * WEBAUTHN BIOMETRIC REGISTER
 * Stores registered biometric public key / credential ID for staff
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

if (empty($_SESSION['staff_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized. Please sign in as a staff member."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$staff_id = $_SESSION['staff_id'];
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: $_POST;

$credential_id = $data['credential_id'] ?? '';
$public_key    = $data['public_key'] ?? ($data['response']['attestationObject'] ?? 'passkey_pubkey');
$device_name   = trim($data['device_name'] ?? '');

if (empty($device_name)) {
    // Detect device type from User-Agent
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (str_contains($ua, 'Android')) {
        $device_name = 'Android Phone Fingerprint';
    } elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
        $device_name = 'iOS Touch ID / Face ID';
    } elseif (str_contains($ua, 'Windows')) {
        $device_name = 'Windows Hello Fingerprint / PIN';
    } elseif (str_contains($ua, 'Macintosh')) {
        $device_name = 'Mac Touch ID';
    } else {
        $device_name = 'Biometric Sensor';
    }
}

if (empty($credential_id)) {
    echo json_encode(["status" => "error", "message" => "Missing credential ID."]);
    exit;
}

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS `staff_biometrics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` VARCHAR(50) NOT NULL,
    `credential_id` VARCHAR(255) NOT NULL,
    `public_key` TEXT NOT NULL,
    `device_name` VARCHAR(150) DEFAULT 'Mobile / PC Fingerprint',
    `counter` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(`staff_id`),
    UNIQUE KEY `uniq_cred` (`credential_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Insert or update credential
$stmt = $conn->prepare("INSERT INTO `staff_biometrics` (staff_id, credential_id, public_key, device_name) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE device_name = VALUES(device_name), created_at = NOW()");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param("ssss", $staff_id, $credential_id, $public_key, $device_name);
if ($stmt->execute()) {
    // Log in audit trail if available
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $audit = $conn->prepare("INSERT INTO `audit_log` (staff_id, full_name, event_type, event_detail, ip_address) VALUES (?, ?, 'biometric_register', ?, ?)");
        if ($audit) {
            $name = $_SESSION['admin'] ?? $staff_id;
            $detail = "Enrolled biometric device: " . $device_name;
            $audit->bind_param("ssss", $staff_id, $name, $detail, $ip);
            $audit->execute();
            $audit->close();
        }
    } catch (Throwable $t) {}

    echo json_encode([
        "status" => "success",
        "message" => "Fingerprint registered successfully! You can now use your thumbprint to clock in.",
        "device_name" => $device_name
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to save fingerprint credential."]);
}
$stmt->close();
