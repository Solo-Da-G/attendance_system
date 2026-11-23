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

// 1. Verify credential + staff profile + branch geofence settings in ONE single fast query
$stmt = $conn->prepare("
    SELECT 
        b.id AS bio_id,
        b.device_name,
        s.full_name,
        s.branch,
        s.clock_lat,
        s.clock_lng,
        s.clock_radius,
        br.latitude AS branch_lat,
        br.longitude AS branch_lng,
        br.radius_meters AS branch_radius
    FROM staff_biometrics b
    JOIN staff s ON b.staff_id = s.staff_id
    LEFT JOIN branches br ON LOWER(TRIM(s.branch)) = LOWER(TRIM(br.branch_name))
    WHERE b.staff_id = ? AND b.credential_id = ?
    LIMIT 1
");

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

// 3. Universal GPS Branch Auto-Detection, WFH Schedule & Geofence Enforcement
$wfh_task      = trim((string)($data['task'] ?? $data['wfh_task'] ?? ''));
if (!empty($wfh_task)) {
    $wfh_task = mb_substr($wfh_task, 0, 50, 'UTF-8');
}
$selected_mode = trim((string)($data['selected_mode'] ?? ''));
$staff_branch  = $bio['branch'] ?? '';
$is_wfh_day    = is_wfh_day_today($conn, $staff_branch);

$geo = auto_detect_gps_branch($conn, $lat, $lng, $staff_id);

$work_mode    = 'office';
$branch_name  = '';
$is_geofenced = 1;

if ($selected_mode === 'wfh' && $is_wfh_day) {
    // Explicitly selected Work From Home on an approved WFH day
    $work_mode    = 'wfh';
    $branch_name  = 'Remote / WFH';
    $is_geofenced = 0;
} elseif ($geo['success']) {
    // Inside registered company branch (ALWAYS allowed 24/7!)
    $work_mode    = 'office';
    $branch_name  = $geo['branch_name'];
    $is_geofenced = 1;
} else {
    // Outside registered branch: Verify WFH eligibility
    if (!$is_wfh_day) {
        $is_general_wfh = is_wfh_day_today($conn);
        $err_msg = $geo['error'];
        if ($is_general_wfh && !empty($staff_branch) && !is_branch_wfh_allowed($conn, $staff_branch)) {
            $err_msg = "🔒 Work From Home is not active for your branch ($staff_branch) today. You must report to an office branch to clock in.";
        }
        // Non-WFH day or WFH disabled: Strictly reject punch!
        echo json_encode([
            "status"  => "error",
            "message" => $err_msg
        ]);
        exit;
    }

    // Today IS an active WFH day: Remote punch permitted
    $work_mode    = 'wfh';
    $branch_name  = 'Remote / WFH';
    $is_geofenced = 0;
}

if ($work_mode === 'wfh') {
    // Check if attendance already completed for today
    $checkToday = $conn->prepare("SELECT id, clock_out FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? ORDER BY id DESC LIMIT 1");
    if ($checkToday) {
        $checkToday->bind_param("ss", $staff_id, $today);
        $checkToday->execute();
        $todayRow = $checkToday->get_result()->fetch_assoc();
        $checkToday->close();
        if ($todayRow && $todayRow['clock_out'] !== null) {
            echo json_encode([
                "status"  => "error",
                "message" => "Attendance already completed for today. The system resets at 12:00 AM."
            ]);
            exit;
        }
        if (!$todayRow) {
            $task_required = get_system_setting($conn, 'wfh_task_required', '0') === '1';
            if ($task_required && empty($wfh_task)) {
                echo json_encode([
                    "status"  => "error",
                    "message" => "📝 Daily Task Summary Required: Please enter your work focus for today."
                ]);
                exit;
            }
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
$stmt = $conn->prepare("SELECT id, clock_in, clock_out, work_mode, branch_in, wfh_task FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ss", $staff_id, $today);
$stmt->execute();
$check = $stmt->get_result();
$record = $check->fetch_assoc();
$stmt->close();

$attendanceColumns = function_exists('db_table_columns') ? db_table_columns($conn, 'attendance') : [];

if (!$record) {
    // ── CLOCK IN ──
    $insertColumns = ['staff_id', 'clock_in', 'status', 'source', 'lat_in', 'lng_in', 'is_geofenced', 'branch_in'];
    $insertValues  = ['?', '?', "'in'", "'fingerprint'", '?', '?', '?', '?'];
    $insertTypes   = 'ssddis';
    $insertParams  = [$staff_id, $current_time, $lat, $lng, $is_geofenced, $branch_name];

    if (in_array('work_mode', $attendanceColumns, true)) {
        $insertColumns[] = 'work_mode';
        $insertValues[]  = '?';
        $insertTypes    .= 's';
        $insertParams[]  = $work_mode;
    }
    if (in_array('wfh_task', $attendanceColumns, true) && !empty($wfh_task)) {
        $insertColumns[] = 'wfh_task';
        $insertValues[]  = '?';
        $insertTypes    .= 's';
        $insertParams[]  = $wfh_task;
    }

    $stmt2 = $conn->prepare("INSERT INTO attendance (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")");
    $stmt2->bind_param($insertTypes, ...$insertParams);
    $stmt2->execute();
    $stmt2->close();

    // Log in audit log
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $audit = $conn->prepare("INSERT INTO `audit_log` (staff_id, full_name, event_type, event_detail, ip_address) VALUES (?, ?, 'clock_in_thumbprint', ?, ?)");
        if ($audit) {
            $name = $_SESSION['admin'] ?? $staff_id;
            $detail = ($work_mode === 'wfh')
                ? "Clocked in remotely (Work From Home) via Thumbprint at " . date('h:i A')
                : "Clocked in at {$branch_name} via Thumbprint at " . date('h:i A');
            $audit->bind_param("ssss", $staff_id, $name, $detail, $ip);
            $audit->execute();
            $audit->close();
        }
    } catch (Throwable $t) {}

    $successMsg = ($work_mode === 'wfh')
        ? "✅ Verified! Clock-in recorded from home (Remote WFH) via Thumbprint at " . date('h:i A', strtotime($current_time))
        : "✅ Verified! Clock-in recorded at {$branch_name} via Thumbprint at " . date('h:i A', strtotime($current_time));

    echo json_encode([
        "status"    => "success",
        "action"    => "in",
        "work_mode" => $work_mode,
        "branch"    => $branch_name,
        "time"      => date('h:i A', strtotime($current_time)),
        "message"   => $successMsg
    ]);
    exit;
} elseif ($record['clock_out'] === null) {
    // ── CLOCK OUT ──
    $clock_in_time = strtotime($record['clock_in']);
    $clock_out_time = strtotime($current_time);
    $diff_seconds = max(0, $clock_out_time - $clock_in_time);
    $total_hours = round($diff_seconds / 3600, 2);

    // ── HYBRID DAY-SHIFT RESOLUTION ──
    $prev_mode   = $record['work_mode'] ?? 'office';
    $prev_branch = $record['branch_in'] ?? 'Office';
    $final_mode  = $prev_mode;

    if ($prev_mode === 'wfh' && $geo['success']) {
        // Started at home, clocked out at office -> HYBRID!
        $final_mode = 'hybrid';
    } elseif ($prev_mode === 'office' && !$geo['success'] && $is_wfh_day) {
        // Started at office, clocked out at home on WFH day -> HYBRID!
        $final_mode = 'hybrid';
    } elseif ($prev_mode === 'wfh' && !$geo['success']) {
        $final_mode = 'wfh';
    } else {
        $final_mode = 'office';
    }

    $updateParts  = ['clock_out = ?', "status = 'out'", "source = 'fingerprint'", 'total_hours = ?', 'lat_out = ?', 'lng_out = ?', 'branch_out = ?'];
    $updateTypes  = 'sddds';
    $updateParams = [$current_time, $total_hours, $lat, $lng, $branch_name];

    if (in_array('work_mode', $attendanceColumns, true)) {
        $updateParts[] = 'work_mode = ?';
        $updateTypes  .= 's';
        $updateParams[] = $final_mode;
    }
    if (in_array('wfh_task', $attendanceColumns, true) && !empty($wfh_task)) {
        $combinedTask = !empty($record['wfh_task']) ? $record['wfh_task'] . " | Evening: " . $wfh_task : $wfh_task;
        $updateParts[] = 'wfh_task = ?';
        $updateTypes  .= 's';
        $updateParams[] = $combinedTask;
    }

    $updateTypes   .= 'i';
    $updateParams[] = (int)$record['id'];

    $stmt3 = $conn->prepare("UPDATE attendance SET " . implode(', ', $updateParts) . " WHERE id = ?");
    $stmt3->bind_param($updateTypes, ...$updateParams);
    $stmt3->execute();
    $stmt3->close();

    // Log in audit log
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $audit = $conn->prepare("INSERT INTO `audit_log` (staff_id, full_name, event_type, event_detail, ip_address) VALUES (?, ?, 'clock_out_thumbprint', ?, ?)");
        if ($audit) {
            $name = $_SESSION['admin'] ?? $staff_id;
            $detail = "Clocked out via Thumbprint. Duration: {$total_hours} hrs. Mode: {$final_mode}. In: {$prev_branch}, Out: {$branch_name}";
            $audit->bind_param("ssss", $staff_id, $name, $detail, $ip);
            $audit->execute();
            $audit->close();
        }
    } catch (Throwable $t) {}

    $modeTag = ($final_mode === 'hybrid')
        ? '🔄 Hybrid Shift'
        : (($final_mode === 'wfh') ? '🏠 Remote WFH' : "🏢 {$branch_name}");

    echo json_encode([
        "status"      => "success",
        "action"      => "out",
        "work_mode"   => $final_mode,
        "branch"      => $branch_name,
        "time"        => date('h:i A', strtotime($current_time)),
        "total_hours" => $total_hours,
        "message"     => "✅ Verified! Clock-out recorded ({$modeTag}) via Thumbprint at " . date('h:i A', strtotime($current_time)) . ". Total work: " . $total_hours . " hrs"
    ]);
    exit;
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Attendance already completed for today. The system resets at 12:00 AM."
    ]);
    exit;
}
