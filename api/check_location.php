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

$staff_branch = '';
$s_stmt = $conn->prepare("SELECT branch FROM staff WHERE staff_id = ? LIMIT 1");
if ($s_stmt) {
    $s_stmt->bind_param("s", $staff_id);
    $s_stmt->execute();
    $staff_branch = $s_stmt->get_result()->fetch_assoc()['branch'] ?? '';
    $s_stmt->close();
}

$is_general_wfh = is_wfh_day_today($conn);
$branch_eligible = function_exists('is_branch_wfh_allowed') ? is_branch_wfh_allowed($conn, $staff_branch) : true;
$is_wfh_day     = $is_general_wfh && $branch_eligible;
$wfh_days       = get_wfh_days($conn);
$wfh_branches   = function_exists('get_wfh_branches') ? get_wfh_branches($conn) : ['all'];

$geo = auto_detect_gps_branch($conn, $lat, $lng, $staff_id);

if ($geo['success']) {
    echo json_encode([
        'status'          => 'success',
        'is_near'         => true,
        'work_mode'       => 'office',
        'branch'          => $geo['branch_name'],
        'distance'        => $geo['distance'],
        'radius'          => $geo['radius'],
        'is_wfh_day'      => $is_wfh_day,
        'is_general_wfh'  => $is_general_wfh,
        'branch_eligible' => $branch_eligible,
        'wfh_days'        => $wfh_days,
        'message'         => "🏢 Inside {$geo['branch_name']} perimeter."
    ]);
} else {
    $status_code = $is_wfh_day ? 'wfh_active' : ($is_general_wfh && !$branch_eligible ? 'branch_not_eligible' : 'not_near');
    $status_msg  = $is_wfh_day 
        ? "🏠 Remote location detected — Work From Home schedule active." 
        : ($is_general_wfh && !$branch_eligible 
            ? "🔒 Remote clocking is not active for your branch (" . ($staff_branch ?: 'Unassigned') . ") today. Please clock in at your branch." 
            : $geo['error']);

    echo json_encode([
        'status'          => $status_code,
        'is_near'         => false,
        'work_mode'       => $is_wfh_day ? 'wfh' : 'locked',
        'branch'          => $geo['branch_name'],
        'distance'        => $geo['distance'],
        'radius'          => $geo['radius'],
        'is_wfh_day'      => $is_wfh_day,
        'is_general_wfh'  => $is_general_wfh,
        'branch_eligible' => $branch_eligible,
        'wfh_days'        => $wfh_days,
        'message'         => $status_msg
    ]);
}
