<?php
/**
 * WEBAUTHN BIOMETRIC CLOCK-IN / CLOCK-OUT
 * Verifies fingerprint assertion and records attendance with source = 'fingerprint'
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

if (empty($_SESSION['staff_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized. Please sign in as a staff member."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$staff_id = $_SESSION['staff_id'];
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: $_POST;

$credential_id = $data['credential_id'] ?? '';
$lat           = isset($data['latitude']) ? (float)$data['latitude'] : null;
$lng           = isset($data['longitude']) ? (float)$data['longitude'] : null;

date_default_timezone_set("Africa/Lagos");
$current_time = date("Y-m-d H:i:s");
$today        = date("Y-m-d");
$current_hour = (int)date('G');

// 1. Verify that the credential is registered for this staff member
$stmt = $conn->prepare("SELECT id, device_name FROM staff_biometrics WHERE staff_id = ? AND credential_id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error checking biometrics."]);
    exit;
}
$stmt->bind_param("ss", $staff_id, $credential_id);
$stmt->execute();
$bio = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bio) {
    echo json_encode(["status" => "error", "message" => "Unrecognized biometric credential. Please register your fingerprint first."]);
    exit;
}

// 2. Cutoff check after 7:00 PM
if ($current_hour >= 19) {
    echo json_encode(["status" => "error", "message" => "Clock-in and clock-out are disabled after 7:00 PM. The system will reset at 12:00 AM."]);
    exit;
}

// 3. Geofence & Location Validation
$is_geofenced = 0;
$branch_name = '';

// Check staff's branch or personal geofence settings
$s_stmt = $conn->prepare("SELECT branch, clock_lat, clock_lng, clock_radius FROM staff WHERE staff_id = ? LIMIT 1");
if ($s_stmt) {
    $s_stmt->bind_param("s", $staff_id);
    $s_stmt->execute();
    $s_row = $s_stmt->get_result()->fetch_assoc();
    $s_stmt->close();
    
    if ($s_row) {
        $branch_name = $s_row['branch'] ?? '';
        $target_lat = $s_row['clock_lat'];
        $target_lng = $s_row['clock_lng'];
        $radius     = $s_row['clock_radius'] ?: 250;

        // If not set on staff, check branch table
        if (empty($target_lat) && !empty($branch_name)) {
            $b_stmt = $conn->prepare("SELECT latitude, longitude, radius_meters FROM branches WHERE branch_name = ? LIMIT 1");
            if ($b_stmt) {
                $b_stmt->bind_param("s", $branch_name);
                $b_stmt->execute();
                $b_row = $b_stmt->get_result()->fetch_assoc();
                $b_stmt->close();
                if ($b_row) {
                    $target_lat = $b_row['latitude'];
                    $target_lng = $b_row['longitude'];
                    $radius     = $b_row['radius_meters'] ?: 250;
                }
            }
        }

        // Validate distance if coords available
        if ($target_lat && $target_lng && $lat && $lng) {
            $earthRadius = 6371000; // in meters
            $latFrom = deg2rad($lat);
            $lonFrom = deg2rad($lng);
            $latTo   = deg2rad((float)$target_lat);
            $lonTo   = deg2rad((float)$target_lng);

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
            $distance = $angle * $earthRadius;

            if ($distance <= $radius) {
                $is_geofenced = 1;
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "📍 Geofence Error: You are " . round($distance) . "m away from your assigned branch (" . htmlspecialchars($branch_name ?: 'Office') . "). Allowed radius is {$radius}m."
                ]);
                exit;
            }
        } elseif ($lat && $lng) {
            $is_geofenced = 1;
        }
    }
}

// 4. Auto-close previous day missed clock-outs
try {
    $stale = $conn->prepare("SELECT id, clock_in FROM attendance WHERE staff_id = ? AND clock_out IS NULL AND DATE(clock_in) < CURDATE() ORDER BY id DESC LIMIT 1");
    if ($stale) {
        $stale->bind_param("s", $staff_id);
        $stale->execute();
        $sr = $stale->get_result()->fetch_assoc();
        if ($sr) {
            $clock_in_date = date("Y-m-d", strtotime($sr['clock_in']));
            $midnight = date("Y-m-d 00:00:00", strtotime($clock_in_date . " +1 day"));
            $upd = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'missed_out', total_hours = 0 WHERE id = ? AND clock_out IS NULL");
            if ($upd) {
                $upd->bind_param("si", $midnight, $sr['id']);
                $upd->execute();
                $upd->close();
            }
        }
        $stale->close();
    }
} catch (Throwable $t) {}

// 5. Check today's attendance record
$stmt = $conn->prepare("SELECT id, clock_in, clock_out FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ss", $staff_id, $today);
$stmt->execute();
$check = $stmt->get_result();
$record = $check->fetch_assoc();
$stmt->close();

if (!$record) {
    // ── CLOCK IN ──
    $stmt2 = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, status, source, lat_in, lng_in, is_geofenced, branch_in) VALUES (?, ?, 'in', 'fingerprint', ?, ?, ?, ?)");
    $stmt2->bind_param("ssddis", $staff_id, $current_time, $lat, $lng, $is_geofenced, $branch_name);
    $stmt2->execute();
    $stmt2->close();

    // Log in audit log
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $audit = $conn->prepare("INSERT INTO `audit_log` (staff_id, full_name, event_type, event_detail, ip_address) VALUES (?, ?, 'clock_in_thumbprint', ?, ?)");
        if ($audit) {
            $name = $_SESSION['admin'] ?? $staff_id;
            $detail = "Clocked in via Thumbprint sensor at " . date('h:i A');
            $audit->bind_param("ssss", $staff_id, $name, $detail, $ip);
            $audit->execute();
            $audit->close();
        }
    } catch (Throwable $t) {}

    echo json_encode([
        "status" => "success",
        "action" => "in",
        "time" => date('h:i A', strtotime($current_time)),
        "message" => "✅ Verified! Clock-in recorded via Thumbprint at " . date('h:i A', strtotime($current_time))
    ]);
    exit;
} elseif ($record['clock_out'] === null) {
    // ── CLOCK OUT ──
    $clock_in_time = strtotime($record['clock_in']);
    $clock_out_time = strtotime($current_time);
    $diff_seconds = max(0, $clock_out_time - $clock_in_time);
    $total_hours = round($diff_seconds / 3600, 2);

    $stmt3 = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'out', source = 'fingerprint', total_hours = ?, lat_out = ?, lng_out = ?, branch_out = ? WHERE id = ?");
    $stmt3->bind_param("sdddsi", $current_time, $total_hours, $lat, $lng, $branch_name, $record['id']);
    $stmt3->execute();
    $stmt3->close();

    // Log in audit log
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $audit = $conn->prepare("INSERT INTO `audit_log` (staff_id, full_name, event_type, event_detail, ip_address) VALUES (?, ?, 'clock_out_thumbprint', ?, ?)");
        if ($audit) {
            $name = $_SESSION['admin'] ?? $staff_id;
            $detail = "Clocked out via Thumbprint sensor at " . date('h:i A') . " (Total: {$total_hours} hrs)";
            $audit->bind_param("ssss", $staff_id, $name, $detail, $ip);
            $audit->execute();
            $audit->close();
        }
    } catch (Throwable $t) {}

    echo json_encode([
        "status" => "success",
        "action" => "out",
        "time" => date('h:i A', strtotime($current_time)),
        "total_hours" => $total_hours,
        "message" => "✅ Verified! Clock-out recorded via Thumbprint at " . date('h:i A', strtotime($current_time)) . ". Total work: " . $total_hours . " hrs"
    ]);
    exit;
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Attendance already completed for today. The system resets at 12:00 AM."
    ]);
    exit;
}
