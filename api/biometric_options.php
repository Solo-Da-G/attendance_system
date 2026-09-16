<?php
/**
 * WEBAUTHN BIOMETRIC OPTIONS
 * Generates cryptographic challenges for registration and authentication
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

if (empty($_SESSION['staff_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized. Please log in as a staff member."]);
    exit;
}

$staff_id = $_SESSION['staff_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Ensure biometrics table exists
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

// Helper function to generate URL-safe Base64
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// -------------------------------------------------------------
// 1. REGISTRATION OPTIONS
// -------------------------------------------------------------
if ($action === 'register_options') {
    $challenge = random_bytes(32);
    $_SESSION['webauthn_reg_challenge'] = base64url_encode($challenge);

    // Get staff profile details
    $stmt = $conn->prepare("SELECT full_name FROM staff WHERE staff_id = ? LIMIT 1");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $display_name = $staff['full_name'] ?? $staff_id;
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (str_contains($host, ':')) {
        $host = explode(':', $host)[0];
    }

    $response = [
        "status" => "success",
        "challenge" => base64url_encode($challenge),
        "rp" => [
            "name" => "TDS Attendance System",
            "id" => $host
        ],
        "user" => [
            "id" => base64url_encode($staff_id),
            "name" => $staff_id,
            "displayName" => $display_name
        ],
        "pubKeyCredParams" => [
            ["type" => "public-key", "alg" => -7],   // ES256
            ["type" => "public-key", "alg" => -257]  // RS256
        ],
        "authenticatorSelection" => [
            "authenticatorAttachment" => "platform", // Prompts internal fingerprint/Touch ID
            "userVerification" => "required",
            "requireResidentKey" => false
        ],
        "timeout" => 60000,
        "attestation" => "none"
    ];

    echo json_encode($response);
    exit;
}

// -------------------------------------------------------------
// 2. AUTHENTICATION / CLOCKING OPTIONS
// -------------------------------------------------------------
if ($action === 'auth_options') {
    $challenge = random_bytes(32);
    $_SESSION['webauthn_auth_challenge'] = base64url_encode($challenge);

    // Retrieve all registered credential IDs for this staff
    $stmt = $conn->prepare("SELECT credential_id FROM staff_biometrics WHERE staff_id = ?");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $allowCredentials = [];
    while ($row = $res->fetch_assoc()) {
        $allowCredentials[] = [
            "type" => "public-key",
            "id" => $row['credential_id'],
            "transports" => ["internal"]
        ];
    }
    $stmt->close();

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (str_contains($host, ':')) {
        $host = explode(':', $host)[0];
    }

    $response = [
        "status" => "success",
        "challenge" => base64url_encode($challenge),
        "rpId" => $host,
        "allowCredentials" => $allowCredentials,
        "userVerification" => "required",
        "timeout" => 60000
    ];

    echo json_encode($response);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action specified."]);
