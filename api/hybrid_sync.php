<?php
/**
 * HYBRID SYNC — Local Bridge Script
 * 
 * This runs on your LOCAL office PC (where the ZKTeco device is connected).
 * It does two things:
 *   1. Syncs ZKTeco device → local database (same as sync_attendance.php)
 *   2. Pushes local attendance data → cloud server API
 * 
 * ===============================================================
 * HOW TO USE:
 * ===============================================================
 * 
 * 1. MANUAL: Open in browser or run from Device Settings page
 * 
 * 2. SCHEDULED (recommended):
 *    Windows Task Scheduler — every 5 minutes:
 *    C:\xamppp\php\php.exe C:\xamppp\htdocs\attendance_system\hybrid_sync.php
 * 
 * 3. URL (from browser):
 *    http://localhost/attendance_system/hybrid_sync.php
 * 
 * ===============================================================
 */

if (php_sapi_name() !== 'cli') {
    
}

include(__DIR__ . "/includes/config.php");
include(__DIR__ . "/lib/ZKTeco.php");

// ================================================================
// CONFIGURATION
// ================================================================
$cloud_url    = CLOUD_URL;          // e.g. https://attendance.yourcompany.com
$api_secret   = API_SECRET;         // Must match the cloud config
$push_api_url = rtrim($cloud_url, '/') . '/api/push_attendance.php';

// Security: only admin or CLI
$is_cli   = (php_sapi_name() === 'cli');
$is_admin = (isset($_SESSION['admin']));

if (!$is_cli && !$is_admin) {
    http_response_code(403);
    die("Unauthorized. Login as admin or run from command line.");
}

logMsg("========== HYBRID SYNC STARTED ==========");

// ================================================================
// STEP 1: SYNC ZKTECO DEVICE → LOCAL DATABASE
// ================================================================
logMsg("STEP 1: Syncing ZKTeco device → local database...");

$devices = $conn->query("SELECT * FROM zk_devices WHERE status = 'active'");
$device_synced = 0;

if ($devices && $devices->num_rows > 0) {
    while ($device = $devices->fetch_assoc()) {
        $zk = new ZKTeco($device['ip_address'], (int)$device['port'], 5);

        if (!$zk->connect()) {
            logMsg("  ❌ Cannot connect to {$device['device_name']} ({$device['ip_address']}): " . $zk->error_message);
            continue;
        }

        $logs = $zk->getAttendance();
        $zk->disconnect();

        if ($logs === false || empty($logs)) {
            logMsg("  ⚠️ No logs from {$device['device_name']}");
            continue;
        }

        $synced = 0;
        foreach ($logs as $log) {
            $fingerprint_id = $log['id'];
            $timestamp = $log['timestamp'];
            $state = $log['state'];

            // Find staff
            $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE fingerprint_id = ?");
            $stmt->bind_param("s", $fingerprint_id);
            $stmt->execute();
            $staff_result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$staff_result) continue;

            $staff_id = $staff_result['staff_id'];
            $date = date("Y-m-d", strtotime($timestamp));

            // Skip duplicates
            $stmt = $conn->prepare("SELECT id FROM attendance WHERE staff_id = ? AND (clock_in = ? OR clock_out = ?)");
            $stmt->bind_param("sss", $staff_id, $timestamp, $timestamp);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) { $stmt->close(); continue; }
            $stmt->close();

            if ($state == 0) {
                // Clock-in
                $stmt = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, status) VALUES (?, ?, 'in')");
                $stmt->bind_param("ss", $staff_id, $timestamp);
                $stmt->execute();
                $stmt->close();
                $synced++;
            } else {
                // Clock-out
                $stmt = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
                $stmt->bind_param("ss", $staff_id, $date);
                $stmt->execute();
                $open = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($open) {
                    $total_hours = round((strtotime($timestamp) - strtotime($open['clock_in'])) / 3600, 2);
                    $stmt = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'out', total_hours = ? WHERE id = ?");
                    $stmt->bind_param("sdi", $timestamp, $total_hours, $open['id']);
                    $stmt->execute();
                    $stmt->close();
                    $synced++;
                }
            }
        }

        $device_synced += $synced;
        logMsg("  ✅ {$device['device_name']}: $synced records synced");
    }
} else {
    logMsg("  ⚠️ No active devices configured");
}

logMsg("STEP 1 COMPLETE: $device_synced total records synced from device(s)");

// ================================================================
// STEP 2: PUSH LOCAL DATA → CLOUD SERVER
// ================================================================
logMsg("");
logMsg("STEP 2: Pushing local data → cloud server ($cloud_url)...");

// Check if cloud URL is configured
if ($cloud_url === 'https://attendance.yourcompany.com') {
    logMsg("  ⚠️ Cloud URL not configured yet. Skipping cloud push.");
    logMsg("  Edit includes/config.php → CLOUD_URL to enable cloud sync.");
    logMsg("========== HYBRID SYNC COMPLETE (local only) ==========");
    exit;
}

// 2a. Push staff data
logMsg("  Pushing staff records...");
$staff_data = [];
$staff_result = $conn->query("SELECT staff_id, full_name, job_title, email, phone, department, branch, fingerprint_id FROM staff");
while ($row = $staff_result->fetch_assoc()) {
    $staff_data[] = $row;
}

$staff_response = cloudPost($push_api_url, [
    'action' => 'push_staff',
    'staff'  => $staff_data
], $api_secret);

if ($staff_response && $staff_response['status'] === 'success') {
    logMsg("  ✅ Staff: " . $staff_response['message']);
} else {
    logMsg("  ❌ Staff push failed: " . ($staff_response['message'] ?? 'Connection error'));
}

// 2b. Push attendance data (last 7 days to catch any gaps)
logMsg("  Pushing attendance records (last 7 days)...");
$seven_days_ago = date("Y-m-d", strtotime("-7 days"));
$stmt = $conn->prepare("SELECT staff_id, clock_in, clock_out, status, total_hours FROM attendance WHERE DATE(clock_in) >= ?");
$stmt->bind_param("s", $seven_days_ago);
$stmt->execute();
$att_result = $stmt->get_result();

$attendance_data = [];
while ($row = $att_result->fetch_assoc()) {
    $attendance_data[] = $row;
}
$stmt->close();

$att_response = cloudPost($push_api_url, [
    'action'  => 'push_attendance',
    'records' => $attendance_data
], $api_secret);

if ($att_response && $att_response['status'] === 'success') {
    logMsg("  ✅ Attendance: " . $att_response['message']);
} else {
    logMsg("  ❌ Attendance push failed: " . ($att_response['message'] ?? 'Connection error'));
}

logMsg("========== HYBRID SYNC COMPLETE ==========");


// ================================================================
// HELPER FUNCTIONS
// ================================================================

/**
 * Send POST request to cloud API
 */
function cloudPost($url, $data, $api_key) {
    $json = json_encode($data);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Api-Key: ' . $api_key
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['status' => 'error', 'message' => "cURL error: $error"];
    }

    if ($http_code !== 200) {
        return ['status' => 'error', 'message' => "HTTP $http_code response"];
    }

    return json_decode($response, true);
}

/**
 * Log message to console/file
 */
function logMsg($msg) {
    $line = "[" . date("Y-m-d H:i:s") . "] $msg";

    if (php_sapi_name() === 'cli') {
        echo $line . "\n";
    }

    // Also write to log file
    $logFile = __DIR__ . "/logs/hybrid_sync.log";
    $logDir  = __DIR__ . "/logs";

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    @file_put_contents($logFile, $line . "\n", FILE_APPEND);
}


