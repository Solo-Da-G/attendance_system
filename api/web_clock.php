<?php
/**
 * WEB CLOCK API (Geofenced)
 * 
 * Handles clock-in/out from the web/mobile dashboard with location verification.
 */
// IMPORTANT:
// "Server returned an invalid response" happens when the response is not valid JSON.
// This can be caused by PHP warnings/notices, HTML error pages, or fatal errors.
// So we buffer ALL output from the very beginning and always emit JSON (even on fatal errors).
error_reporting(E_ALL);
ini_set('display_errors', 0);
// TEMP: helps identify Vercel 500 reasons via JSON

ini_set('log_errors', 1);
@ob_start();

header('Content-Type: application/json');

function json_response($arr, $code = 200) {
    if (function_exists('ob_get_length') && ob_get_length()) {
        @ob_clean(); // remove warnings/notices/whitespace
    }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

// Catch fatal errors and still return JSON
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        json_response([
            'status' => 'error',
            'message' => 'Server error. Please try again.',
            // Comment out details in production if you prefer:
            'debug' => ['type' => $e['type'], 'file' => basename($e['file']), 'line' => $e['line']]
        ], 500);
    }
});

// DEBUG ONLY: persist to a log file so we can read what went wrong on Vercel.
// If FS is not allowed on Vercel, this will silently fail but won't break API.
function dbg_log($msg) { @error_log('[web_clock] ' . $msg); }
include(__DIR__ . "/includes/config.php");
include(__DIR__ . "/lib/Geolocation.php");

// json_response already defined above

// Authorize: require staff_id (admin can clock too, but front-end uses staff clocking)
$staff_id = $_SESSION['staff_id'] ?? null;
$face_verified = ($_POST['face_verified'] ?? '0') === '1';
$photo = trim((string)($_POST['photo'] ?? ''));
$gps_accuracy = isset($_POST['gps_accuracy']) ? (float)$_POST['gps_accuracy'] : null;

if (!$staff_id) {
    // Some flows set role via auth_token restore; allow fallback if staff_id exists
    dbg_log('staff_id missing. cookies auth_token=' . (isset($_COOKIE['auth_token']) ? 'yes' : 'no'));
json_response(["status" => "error", "message" => "Unauthorized: staff session missing.", "debug" => ["hasCookie" => isset($_COOKIE['auth_token']), "keys" => array_keys($_SESSION), "postKeys" => array_keys($_POST) ]], 401);
}
$lat      = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng      = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$action   = $_POST['action'] ?? ''; // 'clock_in' or 'clock_out'

if (!$staff_id) {
    json_response(["status" => "error", "message" => "Staff ID not found in session."], 400);
}

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

// ---------------------------------------------------------------
// AUTO-CLOSE MISSED CLOCK-OUTS (previous day) at 12:00am
// ---------------------------------------------------------------
try {
    $stmtMiss = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
    if ($stmtMiss) {
        $stmtMiss->bind_param("s", $staff_id);
        $stmtMiss->execute();
        $miss = $stmtMiss->get_result()->fetch_assoc();
        $stmtMiss->close();
        if ($miss) {
            $clock_in_date = date("Y-m-d", strtotime($miss['clock_in']));
            $today_date = date("Y-m-d");
            if ($clock_in_date < $today_date) {
                $midnight = date("Y-m-d 00:00:00", strtotime($clock_in_date . " +1 day"));
                $upd = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'missed_out', total_hours = 0 WHERE id = ? AND clock_out IS NULL");
                if ($upd) {
                    $upd->bind_param("si", $midnight, $miss['id']);
                    $upd->execute();
                    $upd->close();
                }
            }
        }
    }
} catch (Throwable $t) {
    // ignore - clocking will still proceed
}

// ---------------------------------------------------------------
// GEOFENCE VALIDATION
// ---------------------------------------------------------------
// Prefer staff's registered clocking spot; fallback to branch geofence
$staffHasClockLat = function_exists('db_has_column') && db_has_column($conn, 'staff', 'clock_lat');
$staffHasClockLng = function_exists('db_has_column') && db_has_column($conn, 'staff', 'clock_lng');
$staffHasClockRadius = function_exists('db_has_column') && db_has_column($conn, 'staff', 'clock_radius');
$staffHasBranchId = function_exists('db_has_column') && db_has_column($conn, 'staff', 'branch_id');
$hasBranchesTable = function_exists('db_has_table') && db_has_table($conn, 'branches');

$selectClockLat = $staffHasClockLat ? 's.clock_lat' : 'NULL AS clock_lat';
$selectClockLng = $staffHasClockLng ? 's.clock_lng' : 'NULL AS clock_lng';
$selectClockRadius = $staffHasClockRadius ? 's.clock_radius' : 'NULL AS clock_radius';
$branchJoin = $hasBranchesTable
    ? ($staffHasBranchId
        ? "LEFT JOIN branches b ON (s.branch = b.branch_name OR s.branch_id = b.id)"
        : "LEFT JOIN branches b ON (s.branch = b.branch_name)")
    : "";
$selectBranchCols = $hasBranchesTable
    ? "b.latitude, b.longitude, b.radius_meters"
    : "NULL AS latitude, NULL AS longitude, NULL AS radius_meters";

$stmt = $conn->prepare("SELECT 
                            {$selectClockLat},
                            {$selectClockLng},
                            {$selectClockRadius},
                            {$selectBranchCols}
                        FROM staff s
                        {$branchJoin}
                        WHERE s.staff_id = ? LIMIT 1");
$locationRow = null;
if ($stmt) {
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $locationRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$locationRow && $hasBranchesTable) {
    // Fallback: If no branch assigned, check if there are ANY branches
    $res = $conn->query("SELECT latitude, longitude, radius_meters FROM branches LIMIT 1");
    $locationRow = $res ? $res->fetch_assoc() : null;
}

if ($locationRow) {
    $targetLat = null;
    $targetLng = null;
    $targetRadius = null;

    if ($locationRow['clock_lat'] !== null && $locationRow['clock_lng'] !== null) {
        $targetLat = (float)$locationRow['clock_lat'];
        $targetLng = (float)$locationRow['clock_lng'];
        $targetRadius = (int)($locationRow['clock_radius'] ?? 300);
    } elseif ($locationRow['latitude'] !== null && $locationRow['longitude'] !== null) {
        $targetLat = (float)$locationRow['latitude'];
        $targetLng = (float)$locationRow['longitude'];
        $targetRadius = (int)($locationRow['radius_meters'] ?? 200);
    }

    if ($targetLat !== null && $targetLng !== null && $targetRadius !== null) {
        $distance = Geolocation::getDistance($lat, $lng, $targetLat, $targetLng);
        $is_near = Geolocation::isWithinRadius($lat, $lng, $targetLat, $targetLng, $targetRadius);
    } else {
        $is_near = true;
        $distance = 0;
    }

    if (!$is_near) {
        json_response([
            "status" => "error", 
            "message" => "You are too far from the office to clock in/out.",
            "debug" => [
                "dist" => round($distance),
                "limit" => $targetRadius,
                "gps_accuracy" => $gps_accuracy
            ]
        ], 400);
    }
}

// ---------------------------------------------------------------
// PROCESS CLOCKING (Same logic as clock.php but for web)
// ---------------------------------------------------------------
$now  = date("Y-m-d H:i:s");
$date = date("Y-m-d");

if ($action === 'clock_in') {
    // Prevent double clock-in: once you clock in, you can't clock in again till 12am the next day.
    $chk = $conn->prepare("SELECT id FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? ORDER BY id DESC LIMIT 1");
    if ($chk) {
        $chk->bind_param("ss", $staff_id, $date);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($exists) {
            json_response(["status" => "error", "message" => "You have already clocked in today. You cannot clock in again until tomorrow."], 400);
        }
    }

    $attendanceColumns = function_exists('db_table_columns') ? db_table_columns($conn, 'attendance') : [];
    $insertColumns = ['staff_id', 'clock_in', 'status'];
    $insertValues = ['?', '?', "'in'"];
    $insertTypes = 'ss';
    $insertParams = [$staff_id, $now];

    if (in_array('source', $attendanceColumns, true)) {
        $insertColumns[] = 'source';
        $insertValues[] = "'mobile'";
    }
    if (in_array('photo_in', $attendanceColumns, true)) {
        $insertColumns[] = 'photo_in';
        $insertValues[] = '?';
        $insertTypes .= 's';
        $insertParams[] = $photo;
    }
    if (in_array('lat_in', $attendanceColumns, true)) {
        $insertColumns[] = 'lat_in';
        $insertValues[] = '?';
        $insertTypes .= 'd';
        $insertParams[] = $lat;
    }
    if (in_array('lng_in', $attendanceColumns, true)) {
        $insertColumns[] = 'lng_in';
        $insertValues[] = '?';
        $insertTypes .= 'd';
        $insertParams[] = $lng;
    }
    if (in_array('is_geofenced', $attendanceColumns, true)) {
        $insertColumns[] = 'is_geofenced';
        $insertValues[] = '1';
    }

    $sql = "INSERT INTO attendance (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_response(["status" => "error", "message" => "Database schema issue. Please run api/fix_database.php on your cloud database."], 500);
    }
    $stmt->bind_param($insertTypes, ...$insertParams);
    if ($stmt->execute()) {
        json_response(["status" => "success", "message" => "Clocked in successfully!"]);
    } else {
        json_response(["status" => "error", "message" => "Database error. Please try again."], 500);
    }
    $stmt->close();

} else if ($action === 'clock_out') {
    // Find latest open clock-in for today
    $stmt = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ss", $staff_id, $date);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($record) {
        $clock_in_time = strtotime($record['clock_in']);
        $total_hours = round((time() - $clock_in_time) / 3600, 2);

        $attendanceColumns = function_exists('db_table_columns') ? db_table_columns($conn, 'attendance') : [];
        $updateParts = ['clock_out = ?', "status = 'out'", 'total_hours = ?'];
        $updateTypes = 'sd';
        $updateParams = [$now, $total_hours];

        if (in_array('source', $attendanceColumns, true)) {
            $updateParts[] = "source = 'mobile'";
        }
        if (in_array('photo_out', $attendanceColumns, true)) {
            $updateParts[] = 'photo_out = ?';
            $updateTypes .= 's';
            $updateParams[] = $photo;
        }
        if (in_array('lat_out', $attendanceColumns, true)) {
            $updateParts[] = 'lat_out = ?';
            $updateTypes .= 'd';
            $updateParams[] = $lat;
        }
        if (in_array('lng_out', $attendanceColumns, true)) {
            $updateParts[] = 'lng_out = ?';
            $updateTypes .= 'd';
            $updateParams[] = $lng;
        }

        $updateTypes .= 'i';
        $updateParams[] = (int)$record['id'];
        $stmt = $conn->prepare("UPDATE attendance SET " . implode(', ', $updateParts) . " WHERE id = ?");
        if (!$stmt) {
            json_response(["status" => "error", "message" => "Database schema issue. Please run api/fix_database.php on your cloud database."], 500);
        }
        $stmt->bind_param($updateTypes, ...$updateParams);
        if ($stmt->execute()) {
            json_response(["status" => "success", "message" => "Clocked out successfully! Total: $total_hours hours"]);
        } else {
            json_response(["status" => "error", "message" => "Database error. Please try again."], 500);
        }
        $stmt->close();
    } else {
        json_response(["status" => "error", "message" => "No active clock-in found for today."], 400);
    }
}

// Nothing should reach here, but just in case:
json_response(["status" => "error", "message" => "Unexpected server flow."], 500);
