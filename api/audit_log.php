<?php
/**
 * AUDIT LOG API
 *
 * Returns audit trail entries for admin/superadmin/branch viewing.
 * GET ?action=fetch&limit=100&filter=today|all
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

$is_admin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'branch'], true);

if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin access required.']);
    exit;
}

// Ensure table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id VARCHAR(50),
        full_name VARCHAR(200),
        event_type VARCHAR(80) NOT NULL,
        event_detail TEXT,
        ip_address VARCHAR(60),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$action = $_GET['action'] ?? 'fetch';
$filter = $_GET['filter'] ?? 'today';
$limit  = min((int)($_GET['limit'] ?? 100), 500);

$where = $filter === 'all' ? '' : "WHERE DATE(created_at) = CURDATE()";

$res = $conn->query("SELECT * FROM audit_log $where ORDER BY created_at DESC LIMIT $limit");
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

echo json_encode(['status' => 'success', 'logs' => $rows, 'total' => count($rows)]);
?>
