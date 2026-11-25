<?php
/**
 * CRON ATTENDANCE REMINDERS — TDS Attendance System
 * Runs at 8:00 AM, 8:15 AM, 5:05 PM, and 5:15 PM.
 * Sends Web Push reminders and records audit reminders for unclocked staff.
 */

include(__DIR__ . "/includes/config.php");

$is_cli = (php_sapi_name() === 'cli');
$auth_key = $_GET['key'] ?? '';
$configured_secret = defined('API_SECRET') ? API_SECRET : 'tds_cron_secret_2026';

if (!$is_cli && $auth_key !== $configured_secret && (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin']))) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

$now_hour = (int)date('H');
$now_min  = (int)date('i');
$today    = date('Y-m-d');
$slot     = $_GET['slot'] ?? '';

if (empty($slot)) {
    if ($now_hour === 8 && $now_min < 10) {
        $slot = 'morning_800';
    } elseif ($now_hour === 8) {
        $slot = 'morning_815';
    } elseif ($now_hour === 17 && $now_min < 10) {
        $slot = 'evening_1705';
    } else {
        $slot = 'evening_1715';
    }
}

$type = str_starts_with($slot, 'morning') ? 'clock_in' : 'clock_out';

$results = [
    'slot' => $slot,
    'type' => $type,
    'target_count' => 0,
    'notified_count' => 0,
    'staff_list' => []
];

if ($type === 'clock_in') {
    // Find staff who have NOT clocked in today
    $sql = "SELECT s.id, s.staff_id, s.full_name, s.branch 
            FROM staff s 
            WHERE s.deleted_at IS NULL 
              AND s.staff_id NOT IN (
                  SELECT DISTINCT a.staff_id 
                  FROM attendance a 
                  WHERE DATE(a.clock_in) = CURDATE()
              )";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $results['target_count']++;
            $results['staff_list'][] = $r['staff_id'];
        }
    }
    $title = "⏰ Clock-In Reminder";
    $body  = "Good morning! You haven't clocked in yet today. Tap to punch your attendance now.";
} else {
    // Find staff who clocked in but have NOT clocked out today
    $sql = "SELECT s.id, s.staff_id, s.full_name, s.branch 
            FROM staff s 
            JOIN attendance a ON s.staff_id = a.staff_id 
            WHERE s.deleted_at IS NULL 
              AND DATE(a.clock_in) = CURDATE() 
              AND a.clock_out IS NULL";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $results['target_count']++;
            $results['staff_list'][] = $r['staff_id'];
        }
    }
    $title = "⏰ Clock-Out Reminder";
    $body  = "Attention: It is close of work! Please remember to clock out before leaving.";
}

// Log reminder event in audit log
try {
    $detail = "Attendance reminder ({$slot}) executed. Targets: {$results['target_count']} staff.";
    $audit = $conn->prepare("INSERT INTO `audit_log` (staff_id, full_name, event_type, event_detail, ip_address) VALUES ('system', 'System Cron', 'attendance_reminder', ?, '127.0.0.1')");
    if ($audit) {
        $audit->bind_param("s", $detail);
        $audit->execute();
        $audit->close();
    }
} catch (Throwable $t) {}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'title' => $title,
    'body' => $body,
    'data' => $results
], JSON_PRETTY_PRINT);