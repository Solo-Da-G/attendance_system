<?php
/**
 * WEBAUTHN BIOMETRIC OPTIONS
 * Generates cryptographic challenges for registration, clocking, and login
 */
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    include(__DIR__ . "/includes/config.php");

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // staff_biometrics table verified in schema

    // Helper function to generate URL-safe Base64
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // -------------------------------------------------------------
    // 0. LOGIN OPTIONS (Public - no active session required)
    // -------------------------------------------------------------
    if ($action === 'login_options') {
        $identifier = trim($_GET['identifier'] ?? $_POST['identifier'] ?? '');
        
        $challenge = random_bytes(32);
        $_SESSION['webauthn_login_challenge'] = base64url_encode($challenge);

        $allowCredentials = [];

        if (!empty($identifier)) {
            // Find credentials for this staff_id, email, or username
            $stmt = $conn->prepare("
                SELECT b.credential_id 
                FROM staff_biometrics b 
                JOIN staff s ON b.staff_id = s.staff_id 
                WHERE s.staff_id = ? OR s.email = ?
            ");
            if ($stmt) {
                $stmt->bind_param("ss", $identifier, $identifier);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $allowCredentials[] = [
                        "type" => "public-key",
                        "id" => $row['credential_id'],
                        "transports" => ["internal"]
                    ];
                }
                $stmt->close();
            }

            if (empty($allowCredentials)) {
                ob_clean();
                echo json_encode([
                    "status" => "error",
                    "message" => "No fingerprint registered for this account yet. Please sign in with password first and register your fingerprint in the dashboard."
                ]);
                exit;
            }
        } else {
            // Global credentials lookup across all registered staff
            $res = $conn->query("SELECT credential_id FROM staff_biometrics LIMIT 10");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $allowCredentials[] = [
                        "type" => "public-key",
                        "id" => $row['credential_id'],
                        "transports" => ["internal"]
                    ];
                }
            }
            if (empty($allowCredentials)) {
                ob_clean();
                echo json_encode([
                    "status" => "error",
                    "message" => "No registered fingerprints found. Please sign in with your password first and register your fingerprint in the dashboard."
                ]);
                exit;
            }
        }

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

        ob_clean();
        echo json_encode($response);
        exit;
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Biometric options error: " . $e->getMessage()]);
    exit;
}

// -------------------------------------------------------------
// Authenticated actions below require active staff session
// -------------------------------------------------------------
if (empty($_SESSION['staff_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized. Please log in as a staff member."]);
    exit;
}

$staff_id = $_SESSION['staff_id'];

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
