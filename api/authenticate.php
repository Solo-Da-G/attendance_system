<?php
/**
 * AUTHENTICATION API
 * 
 * Handles multi-step login:
 * 1. Credentials Verification
 * 2. Face + Geofence Verification
 */
session_start();
include("../includes/config.php");
include("../lib/Geolocation.php");

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'verify_credentials') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Please fill in all fields."]);
        exit;
    }

    // 1. Check Admin/User Table
    $stmt = $conn->prepare("SELECT id, username, password, role FROM admin WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($admin && password_verify($password, $admin['password'])) {
        // Find matching staff record for photo/geo
        $s_stmt = $conn->prepare("SELECT staff_id, photo, full_name, branch FROM staff WHERE staff_id = ? OR full_name = ? LIMIT 1");
        $s_stmt->bind_param("ss", $username, $username);
        $s_stmt->execute();
        $staff = $s_stmt->get_result()->fetch_assoc();
        $s_stmt->close();

        if (!$staff || empty($staff['photo'])) {
            echo json_encode([
                "status" => "error", 
                "message" => "Profile photo not found. Face verification is mandatory for login."
            ]);
            exit;
        }

        // Start Session Immediately
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin']    = $admin['username'];
        $_SESSION['role']     = $admin['role'];
        $_SESSION['staff_id'] = $staff['staff_id'];

        echo json_encode([
            "status"    => "success",
            "message"   => "Login successful!",
            "full_name" => $staff['full_name']
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid username or password."]);
    }
    exit;

} else if ($action === 'finalize_login') {
    if (!isset($_SESSION['temp_auth'])) {
        echo json_encode(["status" => "error", "message" => "Session expired or invalid flow."]);
        exit;
    }

    $is_matched = ($_POST['is_matched'] ?? '') === 'true';
    $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
    $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

    if (!$is_matched) {
        echo json_encode(["status" => "error", "message" => "Face verification failed."]);
        exit;
    }

    if ($lat === null || $lng === null) {
        echo json_encode(["status" => "error", "message" => "Location data missing. Geofencing is mandatory."]);
        exit;
    }

    // Geofence check
    $staff_id = $_SESSION['temp_auth']['staff_id'];
    $stmt = $conn->prepare("SELECT b.latitude, b.longitude, b.radius_meters FROM branches b 
                            JOIN staff s ON (s.branch = b.branch_name OR s.branch_id = b.id)
                            WHERE s.staff_id = ? LIMIT 1");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $branch = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$branch) {
        $res = $conn->query("SELECT latitude, longitude, radius_meters FROM branches LIMIT 1");
        $branch = $res->fetch_assoc();
    }

    if ($branch) {
        $dist = Geolocation::getDistance($lat, $lng, $branch['latitude'], $branch['longitude']);
        if ($dist > $branch['radius_meters']) {
            echo json_encode([
                "status" => "error", 
                "message" => "You are outside the permitted office radius. Login denied.",
                "debug" => ["dist" => round($dist), "limit" => $branch['radius_meters']]
            ]);
            exit;
        }
    }

    // Success! Finalize Session
    $auth = $_SESSION['temp_auth'];
    $_SESSION['admin_id'] = $auth['admin_id'];
    $_SESSION['admin']    = $auth['username'];
    $_SESSION['role']     = $auth['role'];
    $_SESSION['staff_id'] = $auth['staff_id'];
    
    unset($_SESSION['temp_auth']);

    echo json_encode(["status" => "success", "message" => "Authentication successful!"]);
    exit;

} else {
    echo json_encode(["status" => "error", "message" => "Invalid action."]);
}
