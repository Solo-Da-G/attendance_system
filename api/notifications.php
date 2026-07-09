<?php
include(__DIR__ . "/includes/config.php");

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$is_admin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin']);

// Auto-create tables if they don't exist
$conn->query("CREATE TABLE IF NOT EXISTS `admin_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message` TEXT NOT NULL,
    `target` VARCHAR(150) NOT NULL DEFAULT 'all',
    `created_by` VARCHAR(100) NOT NULL DEFAULT 'Admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `notif_reads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `notification_id` INT NOT NULL,
    `user_key` VARCHAR(100) NOT NULL,
    `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_read` (`notification_id`, `user_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';

// ── SEND (admin only) ──────────────────────────────────────────────
if ($action === 'send' && $is_admin) {
    $message    = trim($_POST['message'] ?? '');
    $target     = trim($_POST['target'] ?? 'all');
    $created_by = $_SESSION['admin'] ?? 'Admin';

    if (empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO admin_notifications (message, target, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $message, $target, $created_by);
    echo json_encode($stmt->execute()
        ? ['status' => 'success', 'message' => 'Notification sent!']
        : ['status' => 'error',   'message' => 'DB error: ' . $conn->error]);
    $stmt->close();
    exit;
}

// ── FETCH (any authenticated user) ────────────────────────────────
if ($action === 'fetch') {
    $staff_id    = $_SESSION['staff_id'] ?? null;
    $admin_id    = $_SESSION['admin_id'] ?? null;
    $user_key    = $staff_id ? 's_' . $staff_id : 'a_' . $admin_id;
    $user_branch = '';

    if ($staff_id) {
        $br = $conn->prepare("SELECT branch FROM staff WHERE staff_id = ? LIMIT 1");
        $br->bind_param("s", $staff_id);
        $br->execute();
        $user_branch = $br->get_result()->fetch_assoc()['branch'] ?? '';
        $br->close();
    }

    $stmt = $conn->prepare("
        SELECT n.id, n.message, n.created_by, n.created_at
        FROM admin_notifications n
        LEFT JOIN notif_reads nr ON nr.notification_id = n.id AND nr.user_key = ?
        WHERE n.is_active = 1
          AND nr.id IS NULL
          AND (n.target = 'all' OR n.target = ?)
        ORDER BY n.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("ss", $user_key, $user_branch);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) { $notifications[] = $row; }
    $stmt->close();
    echo json_encode(['status' => 'success', 'notifications' => $notifications]);
    exit;
}

// ── FETCH INBOX (any authenticated user) ──────────────────────────
if ($action === 'fetch_inbox') {
    $staff_id    = $_SESSION['staff_id'] ?? null;
    $admin_id    = $_SESSION['admin_id'] ?? null;
    $user_key    = $staff_id ? 's_' . $staff_id : 'a_' . $admin_id;
    $user_branch = '';

    if ($staff_id) {
        $br = $conn->prepare("SELECT branch FROM staff WHERE staff_id = ? LIMIT 1");
        $br->bind_param("s", $staff_id);
        $br->execute();
        $user_branch = $br->get_result()->fetch_assoc()['branch'] ?? '';
        $br->close();
    }

    $stmt = $conn->prepare("
        SELECT n.id, n.message, n.created_by, n.created_at, nr.read_at
        FROM admin_notifications n
        LEFT JOIN notif_reads nr ON nr.notification_id = n.id AND nr.user_key = ?
        WHERE n.is_active = 1
          AND (n.target = 'all' OR n.target = ?)
          AND (nr.id IS NULL OR nr.read_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $stmt->bind_param("ss", $user_key, $user_branch);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    $unread_count = 0;
    while ($row = $result->fetch_assoc()) { 
        $notifications[] = $row; 
        if (empty($row['read_at'])) $unread_count++;
    }
    $stmt->close();
    echo json_encode(['status' => 'success', 'notifications' => $notifications, 'unread_count' => $unread_count]);
    exit;
}

// ── DISMISS ────────────────────────────────────────────────────────
if ($action === 'dismiss') {
    $notif_id = (int)($_POST['id'] ?? 0);
    $staff_id = $_SESSION['staff_id'] ?? null;
    $admin_id = $_SESSION['admin_id'] ?? null;
    $user_key = $staff_id ? 's_' . $staff_id : 'a_' . $admin_id;

    if ($notif_id > 0) {
        $stmt = $conn->prepare("INSERT IGNORE INTO notif_reads (notification_id, user_key) VALUES (?, ?)");
        $stmt->bind_param("is", $notif_id, $user_key);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
