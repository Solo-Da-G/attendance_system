<?php
/**
 * ZKTeco Attendance Sync Script
 * 
 * This script connects to a ZKTeco biometric device, pulls attendance
 * logs, and inserts them into the attendance database.
 * 
 * Usage:
 *   - Manual:  Run from Device Settings page via the "Sync Now" button
 *   - Cron:    php sync_attendance.php (auto-syncs every X minutes)
 *   - URL:     http://localhost/attendance_system/sync_attendance.php?key=YOUR_API_KEY
 */

// Allow execution from CLI or web
if (php_sapi_name() !== 'cli') {
    
}

include(__DIR__ . "/includes/config.php");
include(__DIR__ . "/lib/ZKTeco.php");

date_default_timezone_set("Africa/Lagos");

// ---------------------------------------------------------------
// SECURITY — Only allow authorized sync
// ---------------------------------------------------------------
$is_cli    = (php_sapi_name() === 'cli');
$is_admin  = (isset($_SESSION['admin']));
$is_api    = (isset($_GET['key']) && validateApiKey($conn, $_GET['key']));
$is_ajax   = (isset($_POST['action']) && $_POST['action'] === 'sync' && $is_admin);

if (!$is_cli && !$is_admin && !$is_api && !$is_ajax) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

// ---------------------------------------------------------------
// GET DEVICE CONFIGURATION
// ---------------------------------------------------------------
$device_id = isset($_POST['device_id']) ? (int)$_POST['device_id'] : 0;

if ($device_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM zk_devices WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $device_id);
    $stmt->execute();
    $devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $conn->query("SELECT * FROM zk_devices WHERE status = 'active'");
    $devices = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

if (empty($devices)) {
    output(["status" => "error", "message" => "No active devices configured. Go to Device Settings to add a device."]);
    exit;
}

// ---------------------------------------------------------------
// SYNC EACH DEVICE
// ---------------------------------------------------------------
$total_synced   = 0;
$total_skipped  = 0;
$total_errors   = 0;
$device_results = [];

foreach ($devices as $device) {
    $result = syncDevice($device, $conn);
    $device_results[] = $result;
    $total_synced  += $result['synced'];
    $total_skipped += $result['skipped'];

    if ($result['status'] === 'error') {
        $total_errors++;
    }
}

output([
    "status"  => ($total_errors < count($devices)) ? "success" : "error",
    "message" => "Synced $total_synced new records, skipped $total_skipped duplicates across " . count($devices) . " device(s)",
    "total_synced"  => $total_synced,
    "total_skipped" => $total_skipped,
    "devices" => $device_results
]);


// ===================================================================
// FUNCTIONS
// ===================================================================

function syncDevice($device, $conn) {
    $ip   = $device['ip_address'];
    $port = (int)$device['port'];
    $name = $device['device_name'];

    $result = [
        'device'  => $name,
        'ip'      => $ip,
        'status'  => 'error',
        'synced'  => 0,
        'skipped' => 0,
        'message' => ''
    ];

    // Connect to device
    $zk = new ZKTeco($ip, $port, 5);

    if (!$zk->connect()) {
        $result['message'] = "Cannot connect to $name ($ip:$port): " . $zk->error_message;
        updateDeviceLastSync($conn, $device['id'], 'Connection failed');
        return $result;
    }

    // Pull attendance logs
    $logs = $zk->getAttendance();

    if ($logs === false) {
        $result['message'] = "Failed to get attendance from $name: " . $zk->error_message;
        $zk->disconnect();
        updateDeviceLastSync($conn, $device['id'], 'Failed to get logs');
        return $result;
    }

    if (empty($logs)) {
        $result['status']  = 'success';
        $result['message'] = "No new attendance records on $name";
        $zk->disconnect();
        updateDeviceLastSync($conn, $device['id'], 'Success - 0 records');
        return $result;
    }

    // Process each log entry
    foreach ($logs as $log) {
        $fingerprint_id = $log['id'];
        $timestamp      = $log['timestamp'];
        $state          = $log['state']; // 0=clock-in, 1=clock-out

        // Find matching staff by fingerprint_id
        $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE fingerprint_id = ?");
        $stmt->bind_param("s", $fingerprint_id);
        $stmt->execute();
        $staff_result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$staff_result) {
            // Unknown fingerprint — skip
            continue;
        }

        $staff_id = $staff_result['staff_id'];
        $date = date("Y-m-d", strtotime($timestamp));

        // Check for duplicate — same staff, same timestamp
        $stmt = $conn->prepare("SELECT id FROM attendance WHERE staff_id = ? AND clock_in = ?");
        $stmt->bind_param("ss", $staff_id, $timestamp);
        $stmt->execute();
        $dup_check = $stmt->get_result();
        $stmt->close();

        if ($dup_check->num_rows > 0) {
            $result['skipped']++;
            continue;
        }

        // Also check if this timestamp was recorded as a clock-out
        $stmt = $conn->prepare("SELECT id FROM attendance WHERE staff_id = ? AND clock_out = ?");
        $stmt->bind_param("ss", $staff_id, $timestamp);
        $stmt->execute();
        $dup_check2 = $stmt->get_result();
        $stmt->close();

        if ($dup_check2->num_rows > 0) {
            $result['skipped']++;
            continue;
        }

        if ($state == 0) {
            // CLOCK-IN — Insert new record
            $stmt = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, status, source) VALUES (?, ?, 'in', 'device')");
            $stmt->bind_param("ss", $staff_id, $timestamp);
            $stmt->execute();
            $stmt->close();
            $result['synced']++;

        } else {
            // CLOCK-OUT — Find the latest open clock-in for this staff
            $stmt = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
            $stmt->bind_param("ss", $staff_id, $date);
            $stmt->execute();
            $open_record = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($open_record) {
                // Calculate total hours
                $clock_in_time  = strtotime($open_record['clock_in']);
                $clock_out_time = strtotime($timestamp);
                $total_hours    = round(($clock_out_time - $clock_in_time) / 3600, 2);

                $stmt = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'out', total_hours = ?, source = IFNULL(source, 'device') WHERE id = ?");
                $stmt->bind_param("sdi", $timestamp, $total_hours, $open_record['id']);
                $stmt->execute();
                $stmt->close();
                $result['synced']++;
            } else {
                // No open clock-in — insert as standalone record with clock-out
                $stmt = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, clock_out, status, total_hours, source) VALUES (?, ?, ?, 'out', 0, 'device')");
                $stmt->bind_param("sss", $staff_id, $timestamp, $timestamp);
                $stmt->execute();
                $stmt->close();
                $result['synced']++;
            }
        }
    }

    $result['status']  = 'success';
    $result['message'] = "Synced {$result['synced']} records from $name ({$result['skipped']} duplicates skipped)";

    // Update last sync time
    updateDeviceLastSync($conn, $device['id'], "Success - {$result['synced']} synced");

    $zk->disconnect();
    return $result;
}

function updateDeviceLastSync($conn, $device_id, $status_msg) {
    $now = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("UPDATE zk_devices SET last_sync = ?, last_sync_status = ? WHERE id = ?");
    $stmt->bind_param("ssi", $now, $status_msg, $device_id);
    $stmt->execute();
    $stmt->close();
}

function validateApiKey($conn, $key) {
    // Simple API key validation from zk_devices table
    if (empty($key)) return false;
    $stmt = $conn->prepare("SELECT id FROM zk_devices WHERE api_key = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return ($result->num_rows > 0);
}

function output($data) {
    if (php_sapi_name() === 'cli') {
        echo "[" . date("Y-m-d H:i:s") . "] " . $data['message'] . "\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}


