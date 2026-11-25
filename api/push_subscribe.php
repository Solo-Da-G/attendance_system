<?php
/**
 * PUSH SUBSCRIBE — Attendance System
 * Stores and manages Web Push subscriptions for mobile reminder alerts.
 */

include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['staff_id']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['staff_id'] ?? ('admin_' . ($_SESSION['admin_id'] ?? '1'));

// Auto-create push_subscriptions table
$conn->query("CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` VARCHAR(50) NOT NULL,
    `endpoint` TEXT NOT NULL,
    `p256dh` VARCHAR(255) NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_staff_endpoint` (`staff_id`, `endpoint`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$action = $data['action'] ?? 'subscribe';

if ($action === 'subscribe') {
    $sub = $data['subscription'] ?? [];
    $endpoint = trim($sub['endpoint'] ?? '');
    $keys = $sub['keys'] ?? [];
    $p256dh = trim($keys['p256dh'] ?? '');
    $auth = trim($keys['auth'] ?? '');
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

    if (empty($endpoint) || empty($p256dh) || empty($auth)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid push subscription payload']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO `push_subscriptions` (staff_id, endpoint, p256dh, auth, user_agent) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent), updated_at = NOW()");
    if ($stmt) {
        $stmt->bind_param("sssss", $user_id, $endpoint, $p256dh, $auth, $ua);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            echo json_encode(['status' => 'success', 'message' => 'Attendance reminders registered on this device!']);
            exit;
        }
    }

    echo json_encode(['status' => 'error', 'message' => 'Failed to save subscription: ' . $conn->error]);
    exit;
} elseif ($action === 'unsubscribe') {
    $endpoint = trim($data['endpoint'] ?? '');
    if (!empty($endpoint)) {
        $stmt = $conn->prepare("DELETE FROM `push_subscriptions` WHERE staff_id = ? AND endpoint = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $user_id, $endpoint);
            $stmt->execute();
            $stmt->close();
        }
    }
    echo json_encode(['status' => 'success', 'message' => 'Reminders disabled on this device.']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unsupported action']);