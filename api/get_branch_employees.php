<?php
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

if (empty($_SESSION['admin_id']) && empty($_SESSION['staff_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$branch = $_GET['branch'] ?? '';
$filter = $_GET['filter'] ?? 'all'; // all, daily_in, daily_out, weekly_in, weekly_out

if ($branch === '') {
    echo json_encode(['status' => 'error', 'message' => 'Branch is required']);
    exit;
}

$branchCondition = ($branch === '__NO_BRANCH__') ? "(s.branch IS NULL OR TRIM(s.branch) = '')" : "LOWER(TRIM(s.branch)) = LOWER(TRIM(?))";

$sql = "SELECT s.staff_id, s.full_name, s.job_title, s.photo FROM staff s";

if ($filter !== 'all') {
    $sql .= " INNER JOIN attendance a ON a.staff_id = s.staff_id";
}

$sql .= " WHERE " . $branchCondition;

if ($filter === 'daily_in') {
    $sql .= " AND DATE(a.clock_in) = CURDATE()";
} elseif ($filter === 'daily_out') {
    $sql .= " AND DATE(a.clock_in) = CURDATE() AND a.clock_out IS NOT NULL AND a.status != 'missed_out'";
} elseif ($filter === 'weekly_in') {
    $sql .= " AND YEARWEEK(a.clock_in, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter === 'weekly_out') {
    $sql .= " AND YEARWEEK(a.clock_in, 1) = YEARWEEK(CURDATE(), 1) AND a.clock_out IS NOT NULL AND a.status != 'missed_out'";
}

$sql .= " GROUP BY s.staff_id ORDER BY s.full_name ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($branch !== '__NO_BRANCH__') {
        $stmt->bind_param("s", $branch);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $employees = [];
    while ($row = $res->fetch_assoc()) {
        $employees[] = [
            'staff_id' => $row['staff_id'],
            'full_name' => $row['full_name'],
            'job_title' => $row['job_title'],
            'photo' => $row['photo'] ? true : false // don't send full base64 to keep response small if not used, or send it if we want to show avatars.
        ];
    }
    echo json_encode(['status' => 'success', 'data' => $employees]);
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
