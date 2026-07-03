<?php
include(__DIR__ . "/includes/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Admin access required.']);
    exit;
}

$type = $_GET['type'] ?? '';
$branch = $_GET['branch'] ?? '';

if ($branch === '__NO_BRANCH__' || $branch === '⚫ NO BRANCH ASSIGNED') {
    $branch = '';
}

$results = [];

if ($type === 'total_staff') {
    $sql = "SELECT s.staff_id, s.full_name, s.branch, s.department FROM staff s ORDER BY s.full_name ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'name' => $row['full_name'],
                'id' => $row['staff_id'],
                'branch' => $row['branch'] ?: 'No Branch',
                'dept' => $row['department']
            ];
        }
    }
} elseif ($type === 'present_today') {
    $sql = "SELECT a.staff_id, s.full_name, s.branch, MIN(a.clock_in) as time_in, MAX(a.clock_out) as time_out, a.status 
            FROM attendance a 
            JOIN staff s ON a.staff_id = s.staff_id 
            WHERE DATE(a.clock_in) = CURDATE() 
            GROUP BY a.staff_id, s.full_name, s.branch, a.status
            ORDER BY s.full_name ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'name' => $row['full_name'],
                'id' => $row['staff_id'],
                'branch' => $row['branch'] ?: 'No Branch',
                'time_in' => $row['time_in'] ? date('g:i A', strtotime($row['time_in'])) : '-',
                'time_out' => $row['time_out'] ? date('g:i A', strtotime($row['time_out'])) : '-',
                'status' => $row['status']
            ];
        }
    }
} elseif ($type === 'absent_today') {
    $sql = "SELECT s.staff_id, s.full_name, s.branch, s.department 
            FROM staff s 
            WHERE s.staff_id NOT IN (
                SELECT DISTINCT staff_id FROM attendance WHERE DATE(clock_in) = CURDATE()
            )
            ORDER BY s.full_name ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'name' => $row['full_name'],
                'id' => $row['staff_id'],
                'branch' => $row['branch'] ?: 'No Branch',
                'dept' => $row['department'],
                'status' => 'absent'
            ];
        }
    }
} elseif ($type === 'total_branches') {
    // Collect stats per branch
    $sql = "SELECT branch_name, created_at FROM branches ORDER BY branch_name ASC";
    // Also include branches from staff table if they don't exist in branches table
    $branches = [];
    if (function_exists('db_has_table') && db_has_table($conn, 'branches')) {
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $branches[$row['branch_name']] = 1;
                $results[] = [
                    'name' => $row['branch_name'],
                    'id' => '-',
                    'branch' => 'Branch',
                    'dept' => 'Registered'
                ];
            }
        }
    }
    $res = $conn->query("SELECT DISTINCT branch FROM staff WHERE branch IS NOT NULL AND branch != ''");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (!isset($branches[$row['branch']])) {
                $results[] = [
                    'name' => $row['branch'],
                    'id' => '-',
                    'branch' => 'Branch',
                    'dept' => 'From Staff'
                ];
            }
        }
    }
} elseif (in_array($type, ['branch_today_in', 'branch_today_out', 'branch_week_in', 'branch_week_out'])) {
    
    $date_filter = "";
    $out_filter = "";
    
    if (strpos($type, 'today') !== false) {
        $date_filter = "DATE(a.clock_in) = CURDATE()";
    } else {
        $date_filter = "YEARWEEK(a.clock_in,1) = YEARWEEK(CURDATE(),1)";
    }
    
    if (strpos($type, 'out') !== false) {
        $out_filter = "AND a.clock_out IS NOT NULL AND a.status != 'missed_out'";
    }
    
    // Exact match for branch or empty if no branch
    if ($branch === '') {
        $branch_cond = "(s.branch IS NULL OR TRIM(s.branch) = '')";
    } else {
        $branch_esc = $conn->real_escape_string($branch);
        $branch_cond = "TRIM(s.branch) = '$branch_esc'";
    }

    $sql = "SELECT a.staff_id, s.full_name, s.branch, MIN(a.clock_in) as time_in, MAX(a.clock_out) as time_out, a.status 
            FROM attendance a 
            JOIN staff s ON a.staff_id = s.staff_id 
            WHERE $date_filter $out_filter AND $branch_cond
            GROUP BY a.staff_id, s.full_name, s.branch, a.status
            ORDER BY s.full_name ASC";
            
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'name' => $row['full_name'],
                'id' => $row['staff_id'],
                'branch' => $row['branch'] ?: 'No Branch',
                'time_in' => $row['time_in'] ? date('g:i A', strtotime($row['time_in'])) : '-',
                'time_out' => $row['time_out'] ? date('g:i A', strtotime($row['time_out'])) : '-',
                'status' => $row['status']
            ];
        }
    }
}

echo json_encode(['status' => 'success', 'data' => $results]);
