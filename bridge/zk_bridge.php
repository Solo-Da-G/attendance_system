<?php
/**
 * ================================================================
 * ZKTECO ZERO-STRESS CLOUD BRIDGE
 * ================================================================
 * 
 * Runs on the office PC connected to the local ZKTeco wall device.
 * NO MySQL required! NO database setup required! NO GitHub required!
 * 
 * How it works:
 * 1. Reads thumbprint logs from the wall device (e.g. 192.168.1.201:4370)
 * 2. Pushes them directly to your live Vercel Cloud site
 * 3. All matching and database calculations happen on the Cloud!
 */

// ================================================================
// 1. CONFIGURATION (Edit these lines if needed)
// ================================================================
$cloud_url   = 'https://attendance-system-delta-five.vercel.app'; // Your live cloud URL
$api_secret  = 'CHANGE_THIS_TO_A_RANDOM_STRING_123';              // Match API_SECRET in includes/config.php
$device_ip   = '192.168.1.201';                                   // IP of your wall ZKTeco machine
$device_port = 4370;                                              // Default ZKTeco port
// ================================================================

require_once __DIR__ . '/ZKTeco.php';

function logMsg($msg) {
    echo "[" . date("Y-m-d H:i:s") . "] " . $msg . "\n";
}

logMsg("Connecting to ZKTeco device at {$device_ip}:{$device_port}...");

$zk = new ZKTeco($device_ip, $device_port, 5);

if (!$zk->connect()) {
    logMsg("❌ Cannot connect to device: " . $zk->error_message);
    logMsg("Please verify device is powered on and Ethernet cable is connected.");
    exit(1);
}

logMsg("✅ Connected! Reading attendance logs...");
$logs = $zk->getAttendance();
$zk->disconnect();

if ($logs === false || empty($logs)) {
    logMsg("ℹ️ No attendance logs found on device.");
    exit(0);
}

logMsg("Found " . count($logs) . " records on device. Pushing to Cloud...");

$push_url = rtrim($cloud_url, '/') . '/api/push_attendance.php';
$payload  = json_encode([
    'action'      => 'push_device_logs',
    'device_name' => 'Wall Device (' . $device_ip . ')',
    'logs'        => $logs
]);

$ch = curl_init($push_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Api-Key: ' . $api_secret
    ],
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err       = curl_error($ch);
curl_close($ch);

if ($http_code === 200 && $response) {
    $res = json_decode($response, true);
    if (!empty($res['status']) && $res['status'] === 'success') {
        logMsg("🎉 SUCCESS: " . $res['message']);
    } else {
        logMsg("⚠️ Cloud response: " . ($res['message'] ?? $response));
    }
} else {
    logMsg("❌ Failed to push to Cloud (HTTP {$http_code}): " . ($err ?: $response));
}
