<?php
/**
 * CLOUD API — Receive Attendance Data
 * 
 * This endpoint runs on your CLOUD server.
 * The local hybrid_sync.php script pushes attendance data here.
 * 
 * Endpoint: POST /api/push_attendance.php
 * Auth: API_SECRET header
 */

include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

// Validate API secret
$headers = getallheaders();
$auth_key = $headers['X-Api-Key'] ?? $headers['x-api-key'] ?? '';

if (empty($auth_key) || $auth_key !== API_SECRET) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid API key"]);
    exit;
}

// Get JSON payload
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid request body"]);
    exit;
}

$action = $input['action'];

// ================================================================
// ACTION: push_attendance — Receive attendance records from local sync
// ================================================================
if ($action === 'push_attendance') {
    $records = $input['records'] ?? [];

    if (empty($records)) {
        echo json_encode(["status" => "success", "message" => "No records to push", "inserted" => 0]);
        exit;
    }

    $inserted = 0;
    $skipped  = 0;

    foreach ($records as $rec) {
        $staff_id    = $rec['staff_id'] ?? '';
        $clock_in    = $rec['clock_in'] ?? null;
        $clock_out   = $rec['clock_out'] ?? null;
        $status      = $rec['status'] ?? 'in';
        $total_hours = $rec['total_hours'] ?? 0;

        if (empty($staff_id) || empty($clock_in)) {
            $skipped++;
            continue;
        }

        // Check for duplicate
        $stmt = $conn->prepare("SELECT id FROM attendance WHERE staff_id = ? AND clock_in = ?");
        $stmt->bind_param("ss", $staff_id, $clock_in);
        $stmt->execute();
        $dup = $stmt->get_result();
        $stmt->close();

        if ($dup->num_rows > 0) {
            // Update clock_out if it was missing
            if ($clock_out) {
                $stmt = $conn->prepare("UPDATE attendance SET clock_out = ?, status = ?, total_hours = ? WHERE staff_id = ? AND clock_in = ? AND clock_out IS NULL");
                $stmt->bind_param("ssdss", $clock_out, $status, $total_hours, $staff_id, $clock_in);
                $stmt->execute();
                $stmt->close();
                if ($conn->affected_rows > 0) $inserted++;
                else $skipped++;
            } else {
                $skipped++;
            }
            continue;
        }

        // Insert new record
        $stmt = $conn->prepare("INSERT INTO attendance (staff_id, clock_in, clock_out, status, total_hours) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssd", $staff_id, $clock_in, $clock_out, $status, $total_hours);

        if ($stmt->execute()) {
            $inserted++;
        } else {
            $skipped++;
        }
        $stmt->close();
    }

    echo json_encode([
        "status"   => "success",
        "message"  => "Pushed $inserted records ($skipped skipped)",
        "inserted" => $inserted,
        "skipped"  => $skipped
    ]);
    exit;
}

// ================================================================
// ACTION: push_staff — Sync staff records to cloud
// ================================================================
if ($action === 'push_staff') {
    $staff_list = $input['staff'] ?? [];
    $synced = 0;

    foreach ($staff_list as $s) {
        $staff_id   = $s['staff_id'] ?? '';
        $full_name  = $s['full_name'] ?? '';
        $job_title  = $s['job_title'] ?? '';
        $email      = $s['email'] ?? '';
        $phone      = $s['phone'] ?? '';
        $department = $s['department'] ?? '';
        $branch     = $s['branch'] ?? '';
        $fingerprint_id = $s['fingerprint_id'] ?? '';

        if (empty($staff_id)) continue;

        // Upsert: insert or update
        $stmt = $conn->prepare("SELECT id FROM staff WHERE staff_id = ?");
        $stmt->bind_param("s", $staff_id);
        $stmt->execute();
        $exists = $stmt->get_result();
        $stmt->close();

        if ($exists->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE staff SET full_name=?, job_title=?, email=?, phone=?, department=?, branch=?, fingerprint_id=? WHERE staff_id=?");
            $stmt->bind_param("ssssssss", $full_name, $job_title, $email, $phone, $department, $branch, $fingerprint_id, $staff_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO staff (staff_id, full_name, job_title, email, phone, department, branch, fingerprint_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $staff_id, $full_name, $job_title, $email, $phone, $department, $branch, $fingerprint_id);
        }

        if ($stmt->execute()) $synced++;
        $stmt->close();
    }

    echo json_encode(["status" => "success", "message" => "Synced $synced staff records", "synced" => $synced]);
    exit;
}

// ================================================================
// ACTION: health — Check if cloud API is running
// ================================================================
if ($action === 'health') {
    echo json_encode([
        "status"  => "success",
        "message" => "Cloud API is running",
        "time"    => date("Y-m-d H:i:s"),
        "env"     => ENVIRONMENT
    ]);
    exit;
}

// Unknown action
http_response_code(400);
echo json_encode(["status" => "error", "message" => "Unknown action: $action"]);
