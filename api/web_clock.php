<?php
/**
 * WEB CLOCK API (Geofenced)
 *
 * Handles clock-in/out from the web/mobile dashboard with location verification.
 *
 * CHANGES:
 * - 6 PM CUTOFF: Rejects clock-in/out after 18:00 server time
 * - MULTIPLE SESSIONS: Up to 3 clock-in/out pairs per day
 * - AUDIT LOGGING: Every clock event is logged to audit_log table
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
@ob_start();

header('Content-Type: application/json');

function json_response($arr, $code = 200) {
    if (function_exists('ob_get_length') && ob_get_length()) {
        @ob_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        json_response([
            'status'  => 'error',
            'message' => 'Server error. Please try again.',
            'debug'   => ['type' => $e['type'], 'file' => basename($e['file']), 'line' => $e['line']]
        ], 500);
    }
});

function dbg_log($msg) { @error_log('[web_clock] ' . $msg); }
include(__DIR__ . "/includes/config.php");
include(__DIR__ . "/lib/Geolocation.php");

// ──────────────────────────────────────────────────────────────────
// AUDIT HELPER
// ──────────────────────────────────────────────────────────────────
function audit_clock_event($conn, $staff_id, $full_name, $event_type, $detail) {
    // Silently create table if missing
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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $s = $conn->prepare("INSERT INTO audit_log (staff_id, full_name, event_type, event_detail, ip_address) VALUES (?,?,?,?,?)");
    if ($s) {
        $s->bind_param("sssss", $staff_id, $full_name, $event_type, $detail, $ip);
        $s->execute();
        $s->close();
    }
}

// ──────────────────────────────────────────────────────────────────
// SESSION & INPUT VALIDATION
// ──────────────────────────────────────────────────────────────────
$staff_id     = $_SESSION['staff_id'] ?? null;
$face_verified = ($_POST['face_verified'] ?? '0') === '1';
$photo        = trim((string)($_POST['photo'] ?? ''));
$gps_accuracy = isset($_POST['gps_accuracy']) ? (float)$_POST['gps_accuracy'] : null;

if (!$staff_id) {
    dbg_log('staff_id missing. cookies auth_token=' . (isset($_COOKIE['auth_token']) ? 'yes' : 'no'));
    json_response(["status" => "error", "message" => "Unauthorized: staff session missing.", "debug" => ["hasCookie" => isset($_COOKIE['auth_token']), "keys" => array_keys($_SESSION), "postKeys" => array_keys($_POST)]], 401);
}

$lat    = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng    = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$action = $_POST['action'] ?? ''; // 'clock_in' or 'clock_out'

if ($lat === null || $lng === null) {
    json_response(["status" => "error", "message" => "Location data missing. Please enable GPS."], 400);
}
if ($action !== 'clock_in' && $action !== 'clock_out') {
    json_response(["status" => "error", "message" => "Invalid action."], 400);
}
if (!$face_verified) {
    json_response(["status" => "error", "message" => "Face verification is required before clocking."], 400);
}
if ($photo !== '' && !str_starts_with($photo, 'data:image/')) {
    json_response(["status" => "error", "message" => "Invalid selfie data received."], 400);
}

// ──────────────────────────────────────────────────────────────────
// 6 PM CUTOFF CHECK (server-side enforcement)
// ──────────────────────────────────────────────────────────────────
$current_hour = (int)date('G'); // 0-23
if ($current_hour >= 18) {
    $full_name_for_log = '';
    $sRow = $conn->prepare("SELECT full_name FROM staff WHERE staff_id = ? LIMIT 1");
    if ($sRow) { $sRow->bind_param("s", $staff_id); $sRow->execute(); $r = $sRow->get_result()->fetch_assoc(); $full_name_for_log = $r['full_name'] ?? ''; $sRow->close(); }
    audit_clock_event($conn, $staff_id, $full_name_for_log, 'blocked_after_6pm', "Attempted $action at " . date('H:i:s'));
    json_response([
        "status"  => "error",
        "message" => "⏰ Clock-in and clock-out are disabled after 6:00 PM. Please contact your admin if you need assistance."
    ], 400);
}

// ──────────────────────────────────────────────────────────────────
// FETCH STAFF DETAILS (for audit log)
// ──────────────────────────────────────────────────────────────────
$staff_full_name = '';
$staffInfo = $conn->prepare("SELECT full_name FROM staff WHERE staff_id = ? LIMIT 1");
if ($staffInfo) {
    $staffInfo->bind_param("s", $staff_id);
    $staffInfo->execute();
    $si = $staffInfo->get_result()->fetch_assoc();
    $staff_full_name = $si['full_name'] ?? '';
    $staffInfo->close();
}

// ──────────────────────────────────────────────────────────────────
// AUTO-CLOSE MISSED CLOCK-OUTS (previous day)
// ──────────────────────────────────────────────────────────────────
try {
    $stmtMiss = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
    if ($stmtMiss) {
        $stmtMiss->bind_param("s", $staff_id);
        $stmtMiss->execute();
        $miss = $stmtMiss->get_result()->fetch_assoc();
        $stmtMiss->close();
        if ($miss) {
            $clock_in_date = date("Y-m-d", strtotime($miss['clock_in']));
            $today_date    = date("Y-m-d");
            if ($clock_in_date < $today_date) {
                $midnight = date("Y-m-d 00:00:00", strtotime($clock_in_date . " +1 day"));
                $upd = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'missed_out', total_hours = 0 WHERE id = ? AND clock_out IS NULL");
                if ($upd) { $upd->bind_param("si", $midnight, $miss['id']); $upd->execute(); $upd->close(); }
            }
        }
    }
} catch (Throwable $t) { /* ignore */ }

// ──────────────────────────────────────────────────────────────────
// CREATE COLUMNS IF MISSING
// ──────────────────────────────────────────────────────────────────
try { $conn->query("ALTER TABLE attendance ADD COLUMN branch_in VARCHAR(100) NULL"); } catch(Exception $e) {}
try { $conn->query("ALTER TABLE attendance ADD COLUMN branch_out VARCHAR(100) NULL"); } catch(Exception $e) {}

// ──────────────────────────────────────────────────────────────────
// GEOFENCE VALIDATION & BRANCH DETECTION
// ──────────────────────────────────────────────────────────────────
$staffHasClockLat    = function_exists('db_has_column') && db_has_column($conn, 'staff', 'clock_lat');
$staffHasClockLng    = function_exists('db_has_column') && db_has_column($conn, 'staff', 'clock_lng');
$staffHasClockRadius = function_exists('db_has_column') && db_has_column($conn, 'staff', 'clock_radius');
$hasBranchesTable    = function_exists('db_has_table') && db_has_table($conn, 'branches');

$clock_branch       = null;
$min_distance       = -1;
$is_near            = false;
$closest_branch_limit = 200;
$actual_distance    = 0;

if ($hasBranchesTable) {
    $res = $conn->query("SELECT branch_name, latitude, longitude, radius_meters FROM branches");
    if ($res) {
        while ($b = $res->fetch_assoc()) {
            $dist = Geolocation::getDistance($lat, $lng, $b['latitude'], $b['longitude']);
            if ($dist <= ($b['radius_meters'] ?? 200)) {
                if ($min_distance === -1 || $dist < $min_distance) {
                    $min_distance = $dist;
                    $clock_branch = $b['branch_name'];
                    $closest_branch_limit = $b['radius_meters'] ?? 200;
                    $actual_distance = $dist;
                }
            }
        }
    }
}

if ($clock_branch) {
    $is_near = true;
} else {
    $stmt = $conn->prepare("SELECT s.clock_lat, s.clock_lng, s.clock_radius, COALESCE(NULLIF(TRIM(s.branch),''), 'No Branch') as branch FROM staff s WHERE s.staff_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $staff_id);
        $stmt->execute();
        $locationRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($locationRow && $locationRow['clock_lat'] !== null && $locationRow['clock_lng'] !== null) {
            $dist = Geolocation::getDistance($lat, $lng, $locationRow['clock_lat'], $locationRow['clock_lng']);
            $rad  = (int)($locationRow['clock_radius'] ?? 300);
            if ($dist <= $rad) {
                $is_near = true;
                $clock_branch = $locationRow['branch'];
                $actual_distance = $dist;
            } else {
                $actual_distance = $dist;
                $closest_branch_limit = $rad;
            }
        }
    }
}

if (!$is_near) {
    audit_clock_event($conn, $staff_id, $staff_full_name, 'location_rejected', "Attempted $action — dist: " . round($actual_distance) . "m, limit: {$closest_branch_limit}m");
    json_response([
        "status"  => "error",
        "message" => "You are too far from any office branch to clock in/out.",
        "debug"   => ["dist" => round($actual_distance), "limit" => $closest_branch_limit, "gps_accuracy" => $gps_accuracy]
    ], 400);
}

// ──────────────────────────────────────────────────────────────────
// PROCESS CLOCKING (up to 3 sessions per day)
// ──────────────────────────────────────────────────────────────────
$now  = date("Y-m-d H:i:s");
$date = date("Y-m-d");

$MAX_SESSIONS_PER_DAY = 3;

if ($action === 'clock_in') {
    // Count today's clock-in records
    $countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ?");
    $todayCount = 0;
    if ($countStmt) {
        $countStmt->bind_param("ss", $staff_id, $date);
        $countStmt->execute();
        $todayCount = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $countStmt->close();
    }

    if ($todayCount >= $MAX_SESSIONS_PER_DAY) {
        json_response([
            "status"  => "error",
            "message" => "You have reached the maximum of {$MAX_SESSIONS_PER_DAY} clock-in sessions for today."
        ], 400);
    }

    // Check if last record today is still open (no clock_out) — prevent double-open
    $lastStmt = $conn->prepare("SELECT id, clock_out FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? ORDER BY id DESC LIMIT 1");
    if ($lastStmt) {
        $lastStmt->bind_param("ss", $staff_id, $date);
        $lastStmt->execute();
        $lastRec = $lastStmt->get_result()->fetch_assoc();
        $lastStmt->close();
        if ($lastRec && $lastRec['clock_out'] === null) {
            json_response([
                "status"  => "error",
                "message" => "You are already clocked in. Please clock out first before clocking in again."
            ], 400);
        }
    }

    $attendanceColumns = function_exists('db_table_columns') ? db_table_columns($conn, 'attendance') : [];

    // Determine session label (1st, 2nd, 3rd)
    $sessionLabels = ['Morning', 'Midday', 'Afternoon'];
    $sessionLabel  = $sessionLabels[min($todayCount, 2)];

    $insertColumns = ['staff_id', 'clock_in', 'status'];
    $insertValues  = ['?', '?', "'in'"];
    $insertTypes   = 'ss';
    $insertParams  = [$staff_id, $now];

    if (in_array('source', $attendanceColumns, true)) { $insertColumns[] = 'source'; $insertValues[] = "'mobile'"; }
    if (in_array('photo_in', $attendanceColumns, true)) { $insertColumns[] = 'photo_in'; $insertValues[] = '?'; $insertTypes .= 's'; $insertParams[] = $photo; }
    if (in_array('lat_in', $attendanceColumns, true))  { $insertColumns[] = 'lat_in';  $insertValues[] = '?'; $insertTypes .= 'd'; $insertParams[] = $lat; }
    if (in_array('lng_in', $attendanceColumns, true))  { $insertColumns[] = 'lng_in';  $insertValues[] = '?'; $insertTypes .= 'd'; $insertParams[] = $lng; }
    if (in_array('is_geofenced', $attendanceColumns, true)) { $insertColumns[] = 'is_geofenced'; $insertValues[] = '1'; }
    if (in_array('branch_in', $attendanceColumns, true)) { $insertColumns[] = 'branch_in'; $insertValues[] = '?'; $insertTypes .= 's'; $insertParams[] = $clock_branch; }

    $sql  = "INSERT INTO attendance (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_response(["status" => "error", "message" => "Database schema issue. Please run api/fix_database.php on your cloud database."], 500);
    }
    $stmt->bind_param($insertTypes, ...$insertParams);
    if ($stmt->execute()) {
        $sessionNum = $todayCount + 1;
        audit_clock_event($conn, $staff_id, $staff_full_name, 'clock_in', "$sessionLabel session #{$sessionNum} at branch: $clock_branch");
        json_response([
            "status"   => "success",
            "message"  => "✅ Clocked in successfully! ($sessionLabel session — {$sessionNum}/{$MAX_SESSIONS_PER_DAY})",
            "session"  => $sessionNum
        ]);
    } else {
        json_response(["status" => "error", "message" => "Database error. Please try again."], 500);
    }
    $stmt->close();

} else if ($action === 'clock_out') {
    // Find the latest open clock-in for today (no clock_out set)
    $stmt = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ss", $staff_id, $date);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($record) {
        $clock_in_time = strtotime($record['clock_in']);
        $total_hours   = round((time() - $clock_in_time) / 3600, 2);

        $attendanceColumns = function_exists('db_table_columns') ? db_table_columns($conn, 'attendance') : [];
        $updateParts  = ['clock_out = ?', "status = 'out'", 'total_hours = ?'];
        $updateTypes  = 'sd';
        $updateParams = [$now, $total_hours];

        if (in_array('source', $attendanceColumns, true)) { $updateParts[] = "source = 'mobile'"; }
        if (in_array('photo_out', $attendanceColumns, true)) { $updateParts[] = 'photo_out = ?'; $updateTypes .= 's'; $updateParams[] = $photo; }
        if (in_array('lat_out', $attendanceColumns, true))  { $updateParts[] = 'lat_out = ?';  $updateTypes .= 'd'; $updateParams[] = $lat; }
        if (in_array('lng_out', $attendanceColumns, true))  { $updateParts[] = 'lng_out = ?';  $updateTypes .= 'd'; $updateParams[] = $lng; }
        if (in_array('branch_out', $attendanceColumns, true)) { $updateParts[] = 'branch_out = ?'; $updateTypes .= 's'; $updateParams[] = $clock_branch; }

        $updateTypes   .= 'i';
        $updateParams[] = (int)$record['id'];
        $stmt = $conn->prepare("UPDATE attendance SET " . implode(', ', $updateParts) . " WHERE id = ?");
        if (!$stmt) {
            json_response(["status" => "error", "message" => "Database schema issue. Please run api/fix_database.php on your cloud database."], 500);
        }
        $stmt->bind_param($updateTypes, ...$updateParams);
        if ($stmt->execute()) {
            // Count remaining sessions
            $sessionsUsed = 0;
            $csStmt = $conn->prepare("SELECT COUNT(*) as c FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ?");
            if ($csStmt) { $csStmt->bind_param("ss", $staff_id, $date); $csStmt->execute(); $sessionsUsed = (int)$csStmt->get_result()->fetch_assoc()['c']; $csStmt->close(); }
            $remaining = $MAX_SESSIONS_PER_DAY - $sessionsUsed;

            audit_clock_event($conn, $staff_id, $staff_full_name, 'clock_out', "Session closed. Duration: {$total_hours}h. Branch: $clock_branch");

            $remMsg = $remaining > 0 ? " ({$remaining} session(s) remaining today)" : " (All {$MAX_SESSIONS_PER_DAY} sessions used for today)";
            json_response([
                "status"  => "success",
                "message" => "✅ Clocked out successfully! Duration: $total_hours hours." . $remMsg,
                "remaining_sessions" => $remaining
            ]);
        } else {
            json_response(["status" => "error", "message" => "Database error. Please try again."], 500);
        }
        $stmt->close();
    } else {
        json_response(["status" => "error", "message" => "No active clock-in found for today. Please clock in first."], 400);
    }
}

json_response(["status" => "error", "message" => "Unexpected server flow."], 500);
