<?php
/**
 * E-CARD / RFID BADGE CLOCK-IN & CLOCK-OUT API
 * Verifies physical RFID / NFC cards and records attendance with source = 'card'
 * Supports USB card readers, Android Web NFC, and kiosk terminals.
 */
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: $_POST;

$card_uid = trim((string)($data['card_uid'] ?? ''));
$lat      = isset($data['latitude']) && is_numeric($data['latitude']) ? (float)$data['latitude'] : null;
$lng      = isset($data['longitude']) && is_numeric($data['longitude']) ? (float)$data['longitude'] : null;

if (empty($card_uid)) {
    echo json_encode(["status" => "error", "message" => "No card scanned. Please tap your card on the reader."]);
    exit;
}

date_default_timezone_set("Africa/Lagos");
$current_time = date("Y-m-d H:i:s");
$today        = date("Y-m-d");
$current_hour = (int)date('G');

// 1. Cutoff check after 7:00 PM
if ($current_hour >= 19) {
    echo json_encode([
        "status" => "error",
        "message" => "Clock-in and clock-out are disabled after 7:00 PM. The system will reset at 12:00 AM."
    ]);
    exit;
}

// 2. Lookup employee by card_uid OR staff_id (allowing staff ID barcodes or card tags)
$stmt = $conn->prepare("
    SELECT 
        s.id,
        s.staff_id,
        s.full_name,
        s.branch,
        s.clock_lat,
        s.clock_lng,
        s.clock_radius,
        br.latitude AS branch_lat,
        br.longitude AS branch_lng,
        br.radius_meters AS branch_radius
    FROM staff s
    LEFT JOIN branches br ON LOWER(TRIM(s.branch)) = LOWER(TRIM(br.branch_name))
    WHERE (s.card_uid = ? OR s.staff_id = ?) 
      AND s.deleted_at IS NULL
    LIMIT 1
");

if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database query error."]);
    exit;
}

$stmt->bind_param("ss", $card_uid, $card_uid);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff) {
    echo json_encode([
        "status" => "error",
        "message" => "Unrecognized E-Card UID (" . htmlspecialchars($card_uid) . "). Please assign this card to an employee in Employee Management."
    ]);
    exit;
}

$staff_id   = $staff['staff_id'];
$full_name  = $staff['full_name'];
$branch_name = $staff['branch'] ?? '';

// 3. Geofence & Location Validation (if branch coordinates are defined)
$is_geofenced = 0;
$target_lat   = !empty($staff['branch_lat']) ? (float)$staff['branch_lat'] : (!empty($staff['clock_lat']) ? (float)$staff['clock_lat'] : null);
$target_lng   = !empty($staff['branch_lng']) ? (float)$staff['branch_lng'] : (!empty($staff['clock_lng']) ? (float)$staff['clock_lng'] : null);
$radius       = !empty($staff['branch_radius']) ? (int)$staff['branch_radius'] : ((int)($staff['clock_radius'] ?? 300) ?: 300);

if ($target_lat !== null && $target_lng !== null && $lat !== null && $lng !== null) {
    $earthRadius = 6371000; // in meters
    $latFrom = deg2rad($lat);
    $lonFrom = deg2rad($lng);
    $latTo   = deg2rad($target_lat);
    $lonTo   = deg2rad($target_lng);

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
} elseif ($lat !== null && $lng !== null) {
    $is_geofenced = 1;
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

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if (!$record) {
    // ── CLOCK IN ──
    $stmt2 = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, status, source, lat_in, lng_in, is_geofenced, branch_in) VALUES (?, ?, 'in', 'card', ?, ?, ?, ?)");
    $stmt2->bind_param("ssddis", $staff_id, $current_time, $lat, $lng, $is_geofenced, $branch_name);
    $stmt2->execute();
    $stmt2->close();

    // Log in audit trail
    try {
        $audit = $conn->prepare("INSERT INTO `audit_log` (staff_id, full_name, event_type, event_detail, ip_address) VALUES (?, ?, 'clock_in_card', ?, ?)");
        if ($audit) {
            $detail = "Clocked in via E-Card ({$card_uid}) at " . date('h:i A');
            $audit->bind_param("ssss", $staff_id, $full_name, $detail, $ip);
            $audit->execute();
            $audit->close();
        }
    } catch (Throwable $t) {}

    echo json_encode([
        "status"     => "success",
        "action"     => "in",
        "staff_id"   => $staff_id,
        "staff_name" => $full_name,
        "time"       => date('h:i A', strtotime($current_time)),
        "branch"     => $branch_name,
        "message"    => "✅ Clock-In Verified! Welcome, {$full_name}. Recorded at " . date('h:i A', strtotime($current_time))
    ]);
    exit;

} elseif ($record['clock_out'] === null) {
    // ── CLOCK OUT ──
    $clock_in_time = strtotime($record['clock_in']);
    $clock_out_time = strtotime($current_time);
    $diff_seconds = max(0, $clock_out_time - $clock_in_time);
    $total_hours = round($diff_seconds / 3600, 2);

    $stmt3 = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'out', source = 'card', total_hours = ?, lat_out = ?, lng_out = ?, branch_out = ? WHERE id = ?");
    $stmt3->bind_param("sdddsi", $current_time, $total_hours, $lat, $lng, $branch_name, $record['id']);
    $stmt3->execute();
    $stmt3->close();

    // Log in audit trail
    try {
        $audit = $conn->prepare("INSERT INTO `audit_log` (staff_id, full_name, event_type, event_detail, ip_address) VALUES (?, ?, 'clock_out_card', ?, ?)");
        if ($audit) {
            $detail = "Clocked out via E-Card ({$card_uid}) at " . date('h:i A') . " (Total: {$total_hours} hrs)";
            $audit->bind_param("ssss", $staff_id, $full_name, $detail, $ip);
            $audit->execute();
            $audit->close();
        }
    } catch (Throwable $t) {}

    echo json_encode([
        "status"      => "success",
        "action"      => "out",
        "staff_id"    => $staff_id,
        "staff_name"  => $full_name,
        "time"        => date('h:i A', strtotime($current_time)),
        "total_hours" => $total_hours,
        "branch"      => $branch_name,
        "message"     => "✅ Clock-Out Verified! Goodbye, {$full_name}. Recorded at " . date('h:i A', strtotime($current_time)) . " (Total: {$total_hours} hrs)"
    ]);
    exit;

} else {
    // Already clocked in and out today
    $inTime  = date('h:i A', strtotime($record['clock_in']));
    $outTime = date('h:i A', strtotime($record['clock_out']));
    echo json_encode([
        "status"     => "info",
        "action"     => "already_completed",
        "staff_id"   => $staff_id,
        "staff_name" => $full_name,
        "message"    => "ℹ️ {$full_name} has already completed attendance today (In: {$inTime}, Out: {$outTime}). Next clock-in starts tomorrow at 12:00 AM."
    ]);
    exit;
}
