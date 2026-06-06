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

session_start();
include("../includes/config.php");
include("../lib/Geolocation.php");

// json_response already defined above

if (!isset($_SESSION['admin'])) {
    json_response(["status" => "error", "message" => "Unauthorized"], 401);
}

$staff_id = $_SESSION['staff_id'] ?? null; // We need to ensure staff_id is in session
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
// Get staff's branch or the default branch
$stmt = $conn->prepare("SELECT b.latitude, b.longitude, b.radius_meters FROM branches b 
                        JOIN staff s ON (s.branch = b.branch_name OR s.branch_id = b.id)
                        WHERE s.staff_id = ? LIMIT 1");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$branch) {
    // Fallback: If no branch assigned, check if there are ANY branches
    $res = $conn->query("SELECT latitude, longitude, radius_meters FROM branches LIMIT 1");
    $branch = $res->fetch_assoc();
}

if ($branch) {
    $is_near = Geolocation::isWithinRadius($lat, $lng, $branch['latitude'], $branch['longitude'], $branch['radius_meters']);
    if (!$is_near) {
        json_response([
            "status" => "error", 
            "message" => "You are too far from the office to clock in/out.",
            "debug" => [
                "dist" => round(Geolocation::getDistance($lat, $lng, $branch['latitude'], $branch['longitude'])),
                "limit" => $branch['radius_meters']
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
    // Prevent double clock-in: if there's an open record already (today), require clock-out.
    $chk = $conn->prepare("SELECT id FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
    if ($chk) {
        $chk->bind_param("ss", $staff_id, $date);
        $chk->execute();
        $open = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($open) {
            json_response(["status" => "error", "message" => "You are already clocked in today. Please clock out first."], 400);
        }
    }

    $stmt = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, status, lat_in, lng_in, is_geofenced) VALUES (?, ?, 'in', ?, ?, 1)");
    $stmt->bind_param("ssdd", $staff_id, $now, $lat, $lng);
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

        $stmt = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'out', total_hours = ?, lat_out = ?, lng_out = ? WHERE id = ?");
        $stmt->bind_param("sdddi", $now, $total_hours, $lat, $lng, $record['id']);
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
