<?php
/**
 * FIELD WORK COMMENT API
 * 
 * Allows staff to submit a field-work note when they may not return to clock out.
 * Admins, superadmins, and branch managers can view these messages.
 *
 * POST actions:
 *   action=submit  — Staff submits a field work comment
 *   action=mark_read&id=N — Admin marks a comment as read
 *
 * GET actions:
 *   ?action=fetch  — Admin fetches all today's comments
 *   ?action=count  — Returns unread count for badge
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

function json_out($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

// ── Ensure tables exist ──────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS field_work_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id VARCHAR(50) NOT NULL,
        full_name VARCHAR(200),
        branch VARCHAR(100),
        comment TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

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

$is_admin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'branch'], true);
$staff_id = $_SESSION['staff_id'] ?? null;
$admin_id = $_SESSION['admin_id'] ?? null;

// ── Helper: log an audit event ────────────────────────────────────
function audit_event($conn, $staff_id, $full_name, $event_type, $detail) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare("INSERT INTO audit_log (staff_id, full_name, event_type, event_detail, ip_address) VALUES (?,?,?,?,?)");
    if ($stmt) {
        $stmt->bind_param("sssss", $staff_id, $full_name, $event_type, $detail, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// ──────────────────────────────────────────────────────────────────
// POST — Submit a field work comment (staff only)
// ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'submit') {
        if (!$staff_id) {
            json_out(['status' => 'error', 'message' => 'Staff session required.'], 401);
        }

        $comment = trim($_POST['comment'] ?? '');
        if (empty($comment)) {
            json_out(['status' => 'error', 'message' => 'Comment cannot be empty.'], 400);
        }
        if (mb_strlen($comment) > 1000) {
            json_out(['status' => 'error', 'message' => 'Comment is too long (max 1000 characters).'], 400);
        }

        // Fetch staff name and branch
        $sRow = null;
        $sStmt = $conn->prepare("SELECT full_name, branch FROM staff WHERE staff_id = ? LIMIT 1");
        if ($sStmt) {
            $sStmt->bind_param("s", $staff_id);
            $sStmt->execute();
            $sRow = $sStmt->get_result()->fetch_assoc();
            $sStmt->close();
        }
        $full_name = $sRow['full_name'] ?? 'Unknown';
        $branch    = $sRow['branch'] ?? '';

        $ins = $conn->prepare("INSERT INTO field_work_comments (staff_id, full_name, branch, comment) VALUES (?,?,?,?)");
        if (!$ins) {
            json_out(['status' => 'error', 'message' => 'Database error.'], 500);
        }
        $ins->bind_param("ssss", $staff_id, $full_name, $branch, $comment);
        if ($ins->execute()) {
            // Write to audit log
            audit_event($conn, $staff_id, $full_name, 'field_work_comment', "Field work note: " . mb_substr($comment, 0, 200));
            json_out(['status' => 'success', 'message' => 'Your field work note has been submitted. Your manager has been notified.']);
        } else {
            json_out(['status' => 'error', 'message' => 'Failed to save comment.'], 500);
        }
        $ins->close();
    }

    if ($action === 'mark_read') {
        if (!$is_admin) {
            json_out(['status' => 'error', 'message' => 'Admin access required.'], 403);
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) json_out(['status' => 'error', 'message' => 'Invalid ID.'], 400);

        $upd = $conn->prepare("UPDATE field_work_comments SET is_read = 1 WHERE id = ?");
        if ($upd) {
            $upd->bind_param("i", $id);
            $upd->execute();
            $upd->close();
        }
        json_out(['status' => 'success']);
    }

    if ($action === 'mark_all_read') {
        if (!$is_admin) {
            json_out(['status' => 'error', 'message' => 'Admin access required.'], 403);
        }
        $conn->query("UPDATE field_work_comments SET is_read = 1 WHERE DATE(created_at) = CURDATE()");
        json_out(['status' => 'success']);
    }

    json_out(['status' => 'error', 'message' => 'Unknown action.'], 400);
}

// ──────────────────────────────────────────────────────────────────
// GET — Fetch comments (admin) or count (badge)
// ──────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

if ($action === 'count') {
    if (!$is_admin) json_out(['status' => 'error', 'message' => 'Admin access required.'], 403);
    $r = $conn->query("SELECT COUNT(*) as c FROM field_work_comments WHERE is_read = 0");
    $count = $r ? (int)$r->fetch_assoc()['c'] : 0;
    json_out(['status' => 'success', 'count' => $count]);
}

if ($action === 'fetch') {
    if (!$is_admin) json_out(['status' => 'error', 'message' => 'Admin access required.'], 403);

    $filter = $_GET['filter'] ?? 'today'; // 'today' or 'all'
    $where  = $filter === 'all' ? '' : "WHERE DATE(created_at) = CURDATE()";

    $res = $conn->query("SELECT * FROM field_work_comments $where ORDER BY created_at DESC LIMIT 100");
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    json_out(['status' => 'success', 'comments' => $rows]);
}

json_out(['status' => 'error', 'message' => 'Unknown request.'], 400);
?>
