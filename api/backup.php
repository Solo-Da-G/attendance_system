<?php
// Suppress deprecation notices so they do not corrupt CSV/ZIP binary streams or JSON/alert outputs
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

include(__DIR__ . "/includes/config.php");

// Ensure auto-backup columns exist in `admin` table
if (!db_has_column($conn, 'admin', 'auto_backup_freq')) {
    $conn->query("ALTER TABLE `admin` ADD COLUMN `auto_backup_freq` VARCHAR(20) DEFAULT 'never'");
}
if (!db_has_column($conn, 'admin', 'auto_backup_type')) {
    $conn->query("ALTER TABLE `admin` ADD COLUMN `auto_backup_type` VARCHAR(30) DEFAULT 'both'");
}
if (!db_has_column($conn, 'admin', 'last_backup_time')) {
    $conn->query("ALTER TABLE `admin` ADD COLUMN `last_backup_time` DATETIME NULL");
}

if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized");
}

$action = $_GET['action'] ?? '';

// -------------------------------------------------------------
// HELPER: Dump database structure & data to SQL
// -------------------------------------------------------------
function dumpDatabase($conn) {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $sql = "-- Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach($tables as $table) {
        $result = $conn->query("SELECT * FROM `$table`");
        $num_fields = $result->field_count;
        
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $row2 = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
        $sql .= $row2[1] . ";\n\n";
        
        while($row = $result->fetch_row()) {
            $sql .= "INSERT INTO `$table` VALUES(";
            for($j=0; $j<$num_fields; $j++) {
                $row[$j] = addslashes((string)$row[$j]);
                $row[$j] = preg_replace("/\n/","\\n",$row[$j]);
                if (isset($row[$j])) {
                    $sql .= '"'.$row[$j].'"' ;
                } else {
                    $sql .= '""';
                }
                if ($j<($num_fields-1)) {
                    $sql .= ',';
                }
            }
            $sql .= ");\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

// -------------------------------------------------------------
// HELPER: Fetch detailed attendance records with staff profiles
// -------------------------------------------------------------
function getAttendanceLogRecords($conn, $month = '', $from = '', $to = '', $branch = '') {
    $where = [];
    $params = [];
    $types = '';

    // Month filter (e.g. '2026-10')
    if (!empty($month) && $month !== 'all') {
        $where[] = "DATE_FORMAT(a.clock_in, '%Y-%m') = ?";
        $params[] = $month;
        $types .= 's';
    } elseif (!empty($from) && !empty($to)) {
        $where[] = "DATE(a.clock_in) BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
        $types .= 'ss';
    }

    if (!empty($branch) && $branch !== 'all') {
        $where[] = "(s.branch = ? OR a.branch_in = ? OR a.branch_out = ?)";
        $params[] = $branch;
        $params[] = $branch;
        $params[] = $branch;
        $types .= 'sss';
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $hasPhotoIn   = function_exists('db_has_column') ? db_has_column($conn, 'attendance', 'photo_in') : false;
    $hasPhotoOut  = function_exists('db_has_column') ? db_has_column($conn, 'attendance', 'photo_out') : false;
    $hasGeofence  = function_exists('db_has_column') ? db_has_column($conn, 'attendance', 'is_geofenced') : false;
    $hasBranchIn  = function_exists('db_has_column') ? db_has_column($conn, 'attendance', 'branch_in') : false;
    $hasBranchOut = function_exists('db_has_column') ? db_has_column($conn, 'attendance', 'branch_out') : false;

    $photoInExpr   = $hasPhotoIn ? "(a.photo_in IS NOT NULL AND a.photo_in != '')" : "0";
    $photoOutExpr  = $hasPhotoOut ? "(a.photo_out IS NOT NULL AND a.photo_out != '')" : "0";
    $branchInExpr  = $hasBranchIn ? "COALESCE(a.branch_in, s.branch, 'Main Branch')" : "COALESCE(s.branch, 'Main Branch')";
    $branchOutExpr = $hasBranchOut ? "COALESCE(a.branch_out, s.branch, 'Main Branch')" : "COALESCE(s.branch, 'Main Branch')";
    $geoExpr       = $hasGeofence ? "a.is_geofenced" : "0";

    $sql = "SELECT a.id, a.staff_id, a.clock_in, a.clock_out, a.status, a.source, a.total_hours,
                   {$photoInExpr} AS has_photo_in,
                   {$photoOutExpr} AS has_photo_out,
                   {$branchInExpr} AS loc_in,
                   {$branchOutExpr} AS loc_out,
                   {$geoExpr} AS is_geofenced,
                   s.full_name, s.job_title, s.department, s.branch, s.email, s.phone
            FROM attendance a
            LEFT JOIN staff s ON a.staff_id = s.staff_id
            {$whereSql}
            ORDER BY a.clock_in DESC";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }

    $records = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $clockInTime = !empty($row['clock_in']) ? date("h:i A", strtotime($row['clock_in'])) : '—';
            $clockOutTime = !empty($row['clock_out']) ? date("h:i A", strtotime($row['clock_out'])) : 'Still Working';
            $date = !empty($row['clock_in']) ? date("Y-m-d", strtotime($row['clock_in'])) : '—';
            $dayName = !empty($row['clock_in']) ? date("l", strtotime($row['clock_in'])) : '—';
            
            $status = 'Completed';
            if (($row['status'] ?? '') === 'missed_out') {
                $status = 'missed(clockout)';
            } elseif (empty($row['clock_out'])) {
                $status = 'Working';
            }

            $src = strtoupper((string)($row['source'] ?? 'WEB'));
            if ($src === 'DEVICE') $src = 'Fingerprint / Biometric';
            elseif ($src === 'MOBILE') $src = 'Mobile App';

            $selfie = (!empty($row['has_photo_in']) || !empty($row['has_photo_out'])) ? 'Yes' : 'No';
            $geofenced = !empty($row['is_geofenced']) ? 'Yes' : 'No';

            $hours = isset($row['total_hours']) && $row['total_hours'] !== null ? number_format((float)$row['total_hours'], 2) . ' hrs' : ($status === 'Working' ? 'In Progress' : '0.00 hrs');

            $records[] = [
                'Log ID'               => $row['id'],
                'Staff ID'             => $row['staff_id'],
                'Full Name'            => $row['full_name'] ?: 'Unknown Staff',
                'Job Title'            => $row['job_title'] ?: '—',
                'Department'           => $row['department'] ?: '—',
                'Assigned Branch'      => $row['branch'] ?: '—',
                'Email'                => $row['email'] ?: '—',
                'Phone'                => $row['phone'] ?: '—',
                'Date'                 => $date,
                'Day'                  => $dayName,
                'Clock In Time'        => $clockInTime,
                'Clock In Branch'      => $row['loc_in'],
                'Clock Out Time'       => $clockOutTime,
                'Clock Out Branch'     => $row['loc_out'],
                'Total Hours'          => $hours,
                'Status'               => $status,
                'Clock Method'         => $src,
                'Geofenced Verified'   => $geofenced,
                'Selfie Verified'      => $selfie
            ];
        }
    }
    return $records;
}

// -------------------------------------------------------------
// HELPER: Fetch detailed employee master directory
// -------------------------------------------------------------
function getEmployeeRecords($conn) {
    $hasDeletedAt = function_exists('db_has_column') ? db_has_column($conn, 'staff', 'deleted_at') : true;
    $hasClockLat  = function_exists('db_has_column') ? db_has_column($conn, 'staff', 'clock_lat') : true;
    $hasFpData    = function_exists('db_has_column') ? db_has_column($conn, 'staff', 'fingerprint_data') : true;
    $hasPhoto     = function_exists('db_has_column') ? db_has_column($conn, 'staff', 'photo') : true;

    $photoSelect = $hasPhoto ? "(s.photo IS NOT NULL AND s.photo != '') AS has_photo" : "0 AS has_photo";
    $fpSelect    = $hasFpData ? "(s.fingerprint_data IS NOT NULL AND s.fingerprint_data != '') AS has_fp" : "0 AS has_fp";
    $geoSelect   = $hasClockLat ? "s.clock_lat, s.clock_lng, s.clock_radius" : "NULL AS clock_lat, NULL AS clock_lng, 300 AS clock_radius";
    $delSelect   = $hasDeletedAt ? "s.deleted_at" : "NULL AS deleted_at";

    $sql = "SELECT s.id, s.staff_id, s.full_name, s.job_title, s.department, s.branch, s.email, s.phone, 
                   s.fingerprint_id, s.created_at,
                   {$photoSelect},
                   {$fpSelect},
                   {$geoSelect},
                   {$delSelect},
                   (SELECT COUNT(*) FROM attendance a WHERE a.staff_id = s.staff_id) AS total_attendance,
                   (SELECT COALESCE(SUM(a.total_hours), 0) FROM attendance a WHERE a.staff_id = s.staff_id) AS total_hours,
                   (SELECT MAX(a.clock_in) FROM attendance a WHERE a.staff_id = s.staff_id) AS last_clock_in,
                   (SELECT MAX(a.clock_out) FROM attendance a WHERE a.staff_id = s.staff_id) AS last_clock_out
            FROM staff s ";

    if ($hasDeletedAt) {
        $sql .= "ORDER BY (s.deleted_at IS NOT NULL), s.full_name ASC";
    } else {
        $sql .= "ORDER BY s.full_name ASC";
    }

    $res = $conn->query($sql);
    $records = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $isDeleted = !empty($row['deleted_at']);
            $status = $isDeleted ? 'Archived (' . date('M d, Y', strtotime($row['deleted_at'])) . ')' : 'Active';
            $bioEnrolled = (!empty($row['fingerprint_id']) || !empty($row['has_fp'])) ? 'Yes' : 'No';
            $photoEnrolled = !empty($row['has_photo']) ? 'Yes' : 'No';

            $geo = 'Branch Default';
            if (!empty($row['clock_lat']) && !empty($row['clock_lng'])) {
                $radius = !empty($row['clock_radius']) ? (int)$row['clock_radius'] : 300;
                $geo = $row['clock_lat'] . ', ' . $row['clock_lng'] . ' (' . $radius . 'm radius)';
            }

            $lastIn = !empty($row['last_clock_in']) ? date('M d, Y h:i A', strtotime($row['last_clock_in'])) : 'Never';
            $lastOut = !empty($row['last_clock_out']) ? date('M d, Y h:i A', strtotime($row['last_clock_out'])) : 'Never';
            $regDate = !empty($row['created_at']) ? date('M d, Y h:i A', strtotime($row['created_at'])) : '—';

            $records[] = [
                'Staff ID'               => $row['staff_id'],
                'Full Name'              => $row['full_name'],
                'Email Address'          => $row['email'] ?: '—',
                'Phone Number'           => $row['phone'] ?: '—',
                'Job Title'              => $row['job_title'] ?: '—',
                'Department'             => $row['department'] ?: '—',
                'Branch'                 => $row['branch'] ?: '—',
                'Employment Status'      => $status,
                'Biometric Device ID'    => $row['fingerprint_id'] ?: 'None',
                'Biometrics Enrolled'    => $bioEnrolled,
                'Profile Photo Enrolled' => $photoEnrolled,
                'Geofence Location'      => $geo,
                'Attendance Sessions'    => (int)$row['total_attendance'],
                'Total Hours Worked'     => number_format((float)$row['total_hours'], 2) . ' hrs',
                'Last Clock In'          => $lastIn,
                'Last Clock Out'         => $lastOut,
                'Registration Date'      => $regDate
            ];
        }
    }
    return $records;
}

// -------------------------------------------------------------
// HELPER: Generate CSV with UTF-8 BOM for Microsoft Excel
// -------------------------------------------------------------
function generateCsvFromRecords($records, $emptyMsg = 'No records found') {
    $out = fopen('php://temp', 'r+');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Microsoft Excel auto-detection
    if (!empty($records)) {
        // Explicitly supply separator, enclosure, and escape character to satisfy PHP 8.4+ deprecation requirements
        fputcsv($out, array_keys($records[0]), ',', '"', "\\");
        foreach ($records as $r) {
            fputcsv($out, array_values($r), ',', '"', "\\");
        }
    } else {
        fputcsv($out, ['Message'], ',', '"', "\\");
        fputcsv($out, [$emptyMsg], ',', '"', "\\");
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    return $csv;
}

// -------------------------------------------------------------
// HELPER: Create ZIP Archive in memory
// -------------------------------------------------------------
function createZipArchive($files) {
    if (!class_exists('ZipArchive')) return false;
    $tempZip = tempnam(sys_get_temp_dir(), 'att_zip_');
    $zip = new ZipArchive();
    if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $data = file_get_contents($tempZip);
        @unlink($tempZip);
        return $data;
    }
    return false;
}

// -------------------------------------------------------------
// HELPER: Send Brevo Email with Attachment
// -------------------------------------------------------------
function sendBrevoEmail($conn, $subject, $htmlContent, $attachments) {
    $apiKey = getenv('BREVO_API_KEY') ?: $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? '';
    if ($apiKey === '') {
        return ['success' => false, 'error' => 'Brevo API not configured in Vercel env vars.'];
    }

    $fromEmail = getenv('BREVO_FROM_EMAIL') ?: $_ENV['BREVO_FROM_EMAIL'] ?? $_SERVER['BREVO_FROM_EMAIL'] ?? 'no-reply@attendance.system';
    $fromName  = getenv('BREVO_FROM_NAME') ?: $_ENV['BREVO_FROM_NAME'] ?? $_SERVER['BREVO_FROM_NAME'] ?? 'Attendance System';

    // Get admin email
    $stmt = $conn->prepare("SELECT email FROM admin WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $adminEmail = $stmt->get_result()->fetch_assoc()['email'] ?? 'admin@localhost';
    $stmt->close();

    $brevoAttachments = [];
    foreach ($attachments as $att) {
        $brevoAttachments[] = [
            'name'    => $att['name'],
            'content' => base64_encode($att['content'])
        ];
    }

    $postData = [
        'sender'      => ['name' => $fromName, 'email' => $fromEmail],
        'to'          => [['email' => $adminEmail, 'name' => 'Admin']],
        'subject'     => $subject,
        'htmlContent' => $htmlContent,
        'attachment'  => $brevoAttachments
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => [
            'api-key: ' . $apiKey,
            'accept: application/json',
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT => 25,
    ]);

    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($code >= 200 && $code < 300) {
        return ['success' => true, 'email' => $adminEmail];
    } else {
        return ['success' => false, 'error' => "HTTP $code: " . substr((string)$resp, 0, 150), 'email' => $adminEmail];
    }
}

// -------------------------------------------------------------
// ACTION: Automated Backup Handler (Cron / Iframe)
// -------------------------------------------------------------
if ($action === 'auto') {
    $stmt = $conn->prepare("SELECT id, auto_backup_freq, auto_backup_type, last_backup_time FROM admin WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $adminRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$adminRow || ($adminRow['auto_backup_freq'] ?? 'never') === 'never') {
        die("Auto backup disabled.");
    }
    
    $freq = $adminRow['auto_backup_freq'];
    $last = $adminRow['last_backup_time'] ? strtotime($adminRow['last_backup_time']) : 0;
    
    $intervals = [
        '24_hours' => 24 * 3600,
        '7_days'   => 7 * 24 * 3600,
        '14_days'  => 14 * 24 * 3600,
        '30_days'  => 30 * 24 * 3600,
        '90_days'  => 90 * 24 * 3600,
        '180_days' => 180 * 24 * 3600,
        '365_days' => 365 * 24 * 3600,
    ];
    
    $required_interval = $intervals[$freq] ?? null;
    if (!$required_interval || (time() - $last) < $required_interval) {
        die("Not time yet.");
    }

    $backupType = $adminRow['auto_backup_type'] ?? 'both';
    $currentMonth = date('Y-m');
    $dateLabel = date('Y-m-d');
    $filesToZip = [];

    // SQL dump
    if ($backupType === 'sql' || $backupType === 'both') {
        $sqlData = dumpDatabase($conn);
        if ($backupType === 'sql') {
            $attachments = [
                ['name' => "attendance_backup_{$dateLabel}.sql.txt", 'content' => $sqlData]
            ];
        } else {
            $filesToZip["database_backup_{$dateLabel}.sql"] = $sqlData;
        }
    }

    // Excel/CSV records
    if ($backupType === 'excel' || $backupType === 'both') {
        $attRecords = getAttendanceLogRecords($conn, $currentMonth);
        $attCsv = generateCsvFromRecords($attRecords, 'No attendance records logged this month.');
        $empRecords = getEmployeeRecords($conn);
        $empCsv = generateCsvFromRecords($empRecords, 'No staff records found.');

        $filesToZip["attendance_records_{$currentMonth}.csv"] = $attCsv;
        $filesToZip["staff_master_directory.csv"] = $empCsv;
    }

    if (!empty($filesToZip)) {
        $zipData = createZipArchive($filesToZip);
        if ($zipData !== false && strlen($zipData) > 0) {
            $zipName = ($backupType === 'both') 
                ? "attendance_complete_backup_{$dateLabel}.zip" 
                : "attendance_records_{$currentMonth}.zip";
            $attachments = [
                ['name' => $zipName, 'content' => $zipData]
            ];
        } else {
            $attachments = [];
            foreach ($filesToZip as $fname => $fdata) {
                $attachments[] = ['name' => $fname, 'content' => $fdata];
            }
        }
    }

    $subject = "Automated System Backup - " . date("M d, Y");
    $html = "<h3>Automated Backup Report</h3>
             <p>Your automated attendance system backup has been successfully generated.</p>
             <ul>
                 <li><strong>Backup Type:</strong> " . strtoupper($backupType) . "</li>
                 <li><strong>Frequency:</strong> " . htmlspecialchars($freq) . "</li>
                 <li><strong>Generated:</strong> " . date("M d, Y h:i A") . "</li>
             </ul>";

    $res = sendBrevoEmail($conn, $subject, $html, $attachments);
    if ($res['success']) {
        $conn->query("UPDATE admin SET last_backup_time = NOW() WHERE id = " . (int)$_SESSION['admin_id']);
        die("Auto backup sent successfully.");
    } else {
        die("Auto backup error: " . $res['error']);
    }
}

// -------------------------------------------------------------
// ACTION: Download Detailed Attendance Log (Month or All-Time)
// -------------------------------------------------------------
if ($action === 'attendance_excel' || $action === 'attendance_download') {
    $month  = trim($_GET['month'] ?? date('Y-m'));
    $from   = trim($_GET['from'] ?? '');
    $to     = trim($_GET['to'] ?? '');
    $branch = trim($_GET['branch'] ?? '');
    $isZip  = (!empty($_GET['zip']) && $_GET['zip'] == '1');

    $records = getAttendanceLogRecords($conn, $month, $from, $to, $branch);
    $csvData = generateCsvFromRecords($records, 'No attendance records found for this period.');

    $fileLabel = ($month === 'all') ? 'all_time' : (!empty($month) ? $month : date('Y-m-d'));

    if ($isZip) {
        $zipData = createZipArchive([
            "attendance_records_{$fileLabel}.csv" => $csvData
        ]);
        if ($zipData !== false && strlen($zipData) > 0) {
            $zipFilename = "attendance_records_{$fileLabel}.zip";
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
            header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
            header('Pragma: public');
            echo $zipData;
            exit;
        }
        // Fallback: stream uncompressed CSV if ZipArchive is unavailable
    }
    $filename = "attendance_records_{$fileLabel}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
    header('Pragma: public');
    echo $csvData;
    exit;
}

// -------------------------------------------------------------
// ACTION: Email Attendance Logs (Compressed ZIP / CSV)
// -------------------------------------------------------------
if ($action === 'email_attendance') {
    $month  = trim($_GET['month'] ?? date('Y-m'));
    $from   = trim($_GET['from'] ?? '');
    $to     = trim($_GET['to'] ?? '');
    $branch = trim($_GET['branch'] ?? '');

    $records = getAttendanceLogRecords($conn, $month, $from, $to, $branch);
    $csvData = generateCsvFromRecords($records, 'No attendance records found for this period.');
    $count   = count($records);

    $fileLabel = ($month === 'all') ? 'all_time' : (!empty($month) ? $month : date('Y-m-d'));
    $zipData = createZipArchive([
        "attendance_records_{$fileLabel}.csv" => $csvData
    ]);

    $monthName = ($month === 'all') ? 'All Time (Full History)' : date('F Y', strtotime($month . '-01'));
    $subject = "Attendance Records Report ({$monthName}) - " . date("M d, Y");
    $html = "<h3>Attendance Records Report</h3>
             <p>Attached is the spreadsheet backup for <strong>{$monthName}</strong>.</p>
             <p>Total logged attendance sessions: <strong>{$count} records</strong></p>
             <p>The attached file contains complete staff information, clock-in, clock-out, branches, and total hours.</p>
             <p><small>Generated on " . date("M d, Y h:i A") . "</small></p>";

    if ($zipData !== false && strlen($zipData) > 0) {
        $attachments = [
            ['name' => "attendance_records_{$fileLabel}.zip", 'content' => $zipData]
        ];
    } else {
        $attachments = [
            ['name' => "attendance_records_{$fileLabel}.csv", 'content' => $csvData]
        ];
    }

    $res = sendBrevoEmail($conn, $subject, $html, $attachments);
    if ($res['success']) {
        echo "<!DOCTYPE html><html><body><script>alert('✅ Attendance records for {$monthName} ({$count} records) sent successfully to {$res['email']}'); window.location.href='settings.php';</script></body></html>";
    } else {
        $safeResp = json_encode($res['error']);
        echo "<!DOCTYPE html><html><body><script>alert('❌ Failed to send attendance backup.\\n\\nDetails: ' + " . $safeResp . "); window.location.href='settings.php';</script></body></html>";
    }
    exit;
}

// -------------------------------------------------------------
// ACTION: Download Employee Master Directory (Excel / CSV)
// -------------------------------------------------------------
if ($action === 'employees_excel' || $action === 'employees_download') {
    $records = getEmployeeRecords($conn);
    $isZip = (!empty($_GET['zip']) && $_GET['zip'] == '1');
    $csvData = generateCsvFromRecords($records, 'No staff records found in database.');
    $filename = "staff_directory_backup_" . date("Y-m-d") . ".csv";

    if ($isZip) {
        $zipData = createZipArchive([$filename => $csvData]);
        if ($zipData !== false && strlen($zipData) > 0) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="staff_directory_backup_' . date("Y-m-d") . '.zip"');
            echo $zipData;
            exit;
        }
        // Fallback: stream uncompressed CSV if ZipArchive is unavailable
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
    header('Pragma: public');
    echo $csvData;
    exit;
}

// -------------------------------------------------------------
// ACTION: Email Employee Master Directory
// -------------------------------------------------------------
if ($action === 'email_employees') {
    $records  = getEmployeeRecords($conn);
    $csvData  = generateCsvFromRecords($records, 'No staff records found in database.');
    $count    = count($records);
    $zipData  = createZipArchive([
        "staff_directory_backup_" . date("Y-m-d") . ".csv" => $csvData
    ]);

    $subject = "Staff Directory Backup - " . date("M d, Y");
    $html = "<h3>Staff Master Directory Backup</h3>
             <p>Attached is the spreadsheet containing <strong>{$count} employee profiles</strong> with contact details, branches, departments, and enrollment status.</p>
             <p><small>Generated on " . date("M d, Y h:i A") . "</small></p>";

    if ($zipData !== false && strlen($zipData) > 0) {
        $attachments = [
            ['name' => "staff_directory_backup_" . date("Y-m-d") . ".zip", 'content' => $zipData]
        ];
    } else {
        $attachments = [
            ['name' => "staff_directory_backup_" . date("Y-m-d") . ".csv", 'content' => $csvData]
        ];
    }

    $res = sendBrevoEmail($conn, $subject, $html, $attachments);
    if ($res['success']) {
        echo "<!DOCTYPE html><html><body><script>alert('✅ Staff Directory ({$count} staff profiles) sent successfully to {$res['email']}'); window.location.href='settings.php';</script></body></html>";
    } else {
        $safeResp = json_encode($res['error']);
        echo "<!DOCTYPE html><html><body><script>alert('❌ Failed to send staff backup.\\n\\nDetails: ' + " . $safeResp . "); window.location.href='settings.php';</script></body></html>";
    }
    exit;
}

// -------------------------------------------------------------
// ACTION: Download SQL Database Backup
// -------------------------------------------------------------
if ($action === 'download') {
    $sql_dump = dumpDatabase($conn);
    $filename = "attendance_backup_" . date("Y-m-d_H-i") . ".sql";
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $sql_dump;
    exit;
}

// -------------------------------------------------------------
// ACTION: Email SQL Database Backup
// -------------------------------------------------------------
if ($action === 'email') {
    $sql_dump = dumpDatabase($conn);
    $filename = "attendance_backup_" . date("Y-m-d_H-i") . ".sql";

    $subject = "Database Backup - " . date("M d, Y");
    $html = "<h3>Database Backup</h3><p>Attached is your latest attendance database backup (.sql).</p>";
    $attachments = [
        ['name' => $filename . '.txt', 'content' => $sql_dump]
    ];

    $res = sendBrevoEmail($conn, $subject, $html, $attachments);
    if ($res['success']) {
        echo "<!DOCTYPE html><html><body><script>alert('✅ Database backup sent successfully to {$res['email']}'); window.location.href='dashboard.php';</script></body></html>";
    } else {
        $safeResp = json_encode($res['error']);
        echo "<!DOCTYPE html><html><body><script>alert('❌ Failed to send database backup.\\n\\nDetails: ' + " . $safeResp . "); window.location.href='dashboard.php';</script></body></html>";
    }
    exit;
}
?>
