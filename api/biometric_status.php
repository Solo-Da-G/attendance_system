<?php
/**
 * WEBAUTHN BIOMETRIC STATUS & DEVICE MANAGER
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

if (empty($_SESSION['staff_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$staff_id = $_SESSION['staff_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

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

if ($action === 'delete') {
    $cred_id = $_POST['credential_id'] ?? '';
    if (!empty($cred_id)) {
        $stmt = $conn->prepare("DELETE FROM staff_biometrics WHERE staff_id = ? AND credential_id = ?");
        $stmt->bind_param("ss", $staff_id, $cred_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(["status" => "success", "message" => "Device biometric deleted."]);
        exit;
    }
}

// Fetch status & list of devices
$stmt = $conn->prepare("SELECT id, credential_id, device_name, created_at FROM staff_biometrics WHERE staff_id = ? ORDER BY id DESC");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$res = $stmt->get_result();

$devices = [];
while ($r = $res->fetch_assoc()) {
    $devices[] = [
        "id" => $r['id'],
        "credential_id" => $r['credential_id'],
        "device_name" => $r['device_name'],
        "created_at" => $r['created_at']
    ];
}
$stmt->close();

echo json_encode([
    "status" => "success",
    "enrolled" => count($devices) > 0,
    "count" => count($devices),
    "devices" => $devices
]);
