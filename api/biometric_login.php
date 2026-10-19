<?php
/**
 * WEBAUTHN BIOMETRIC LOGIN API
 * Authenticates user via registered platform fingerprint sensor / Passkey
 */
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    include(__DIR__ . "/includes/config.php");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        exit;
    }

    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true) ?: $_POST;

    $credential_id = trim($data['credential_id'] ?? '');

    if (empty($credential_id)) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "No credential ID received from biometric sensor."]);
        exit;
    }

    // 1. Look up credential in staff_biometrics joined with staff
    $stmt = $conn->prepare("
        SELECT b.staff_id, b.device_name, s.id AS staff_db_id, s.full_name, s.deleted_at
        FROM staff_biometrics b
        JOIN staff s ON b.staff_id = s.staff_id
        WHERE b.credential_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Database query error: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("s", $credential_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        ob_clean();
        echo json_encode([
            "status" => "error",
            "message" => "Unrecognized fingerprint credential. Please sign in with your password and register your device in the dashboard."
        ]);
        exit;
    }

    if (!empty($user['deleted_at'])) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Your staff account has been deactivated. Contact an administrator."]);
        exit;
    }

    // 2. Establish authenticated session
    session_regenerate_id(true);
    $_SESSION['staff_id']   = $user['staff_id'];
    $_SESSION['admin']      = $user['full_name'];
    $_SESSION['role']       = 'staff';
    $_SESSION['login_time'] = time();

    // 3. Issue persistent auth_token for Vercel stateless session restoration
    $token = bin2hex(random_bytes(32));
    if (function_exists('db_has_column') && db_has_column($conn, 'staff', 'auth_token')) {
        $upd = $conn->prepare("UPDATE `staff` SET auth_token = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param("si", $token, $user['staff_db_id']);
            $upd->execute();
            $upd->close();
        }
    }

    $secure = function_exists('is_https_request') ? is_https_request() : true;
    setcookie('auth_token', 'staff_' . $token, [
        'expires' => time() + (86400 * 30),
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ob_clean();
    echo json_encode([
        "status" => "success",
        "staff_id" => $user['staff_id'],
        "message" => "Fingerprint verified! Welcome, " . $user['full_name'] . ".",
        "redirect" => "dashboard.php"
    ]);
    exit;

} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        "status" => "error",
        "message" => "Authentication exception: " . $e->getMessage()
    ]);
    exit;
}
