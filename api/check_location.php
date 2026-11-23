<?php
include(__DIR__ . "/includes/config.php");
include(__DIR__ . "/includes/auto_detect_branch.php");

header('Content-Type: application/json');

if (empty($_SESSION['staff_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Staff login required.']);
    exit;
}

$staff_id = $_SESSION['staff_id'];
$lat      = isset($_POST['lat']) ? (float)$_POST['lat'] : (isset($_GET['lat']) ? (float)$_GET['lat'] : null);
$lng      = isset($_POST['lng']) ? (float)$_POST['lng'] : (isset($_GET['lng']) ? (float)$_GET['lng'] : null);

if ($lat === null || $lng === null) {
    echo json_encode(['status' => 'error', 'message' => 'GPS coordinates missing.']);
    exit;
}

$is_wfh_day = is_wfh_day_today($conn);
$wfh_days   = get_wfh_days($conn);

$geo = auto_detect_gps_branch($conn, $lat, $lng, $staff_id);

if ($geo['success']) {
    echo json_encode([
        'status'      => 'success',
        'is_near'     => true,
        'work_mode'   => 'office',
        'branch'      => $geo['branch_name'],
        'distance'    => $geo['distance'],
        'radius'      => $geo['radius'],
        'is_wfh_day'  => $is_wfh_day,
        'wfh_days'    => $wfh_days,
        'message'     => "🏢 Inside {$geo['branch_name']} perimeter."
    ]);
} else {
    echo json_encode([
        'status'      => $is_wfh_day ? 'wfh_active' : 'not_near',
        'is_near'     => false,
        'work_mode'   => $is_wfh_day ? 'wfh' : 'locked',
        'branch'      => $geo['branch_name'],
        'distance'    => $geo['distance'],
        'radius'      => $geo['radius'],
        'is_wfh_day'  => $is_wfh_day,
        'wfh_days'    => $wfh_days,
        'message'     => $is_wfh_day 
            ? "🏠 Remote location detected — Work From Home schedule active." 
            : $geo['error']
    ]);
}
