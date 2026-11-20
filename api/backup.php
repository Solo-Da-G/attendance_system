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
    @ini_set('memory_limit', '512M');
    @set_time_limit(180);

    // Ensure standard MySQL mode without ANSI_QUOTES so backticks are used
    @$conn->query("SET SESSION sql_mode=''");

    $tables = [];
    $result = $conn->query("SHOW TABLES");
    if (!$result) return "-- Error fetching tables: " . $conn->error;

    while($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $sql = "-- Database Backup for Migration\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "USE test;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach($tables as $table) {
        $result = $conn->query("SELECT * FROM `$table`");
        if (!$result) continue;
        
        $fields = $result->fetch_fields();
        $num_fields = count($fields);
        $field_names = [];
        foreach($fields as $f) {
            $field_names[] = $f->name;
        }
        
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $row2_res = $conn->query("SHOW CREATE TABLE `$table`");
        if ($row2_res && ($row2 = $row2_res->fetch_row())) {
            $createTableSql = $row2[1];
            // Convert any double-quoted identifiers to standard MySQL backticks for full TiDB Cloud compatibility
            $createTableSql = preg_replace('/"([a-zA-Z0-9_]+)"/', '`$1`', $createTableSql);
            $sql .= $createTableSql . ";\n\n";
        }
        
        while($row = $result->fetch_row()) {
            $sql .= "INSERT INTO `$table` VALUES(";
            for($j=0; $j<$num_fields; $j++) {
                $colName = $field_names[$j] ?? '';
                // Skip massive multi-megabyte base64 webcam selfies in attendance to prevent Vercel 500 error
                if ($table === 'attendance' && ($colName === 'photo_in' || $colName === 'photo_out')) {
                    $sql .= 'NULL';
                } elseif ($row[$j] === null) {
                    $sql .= 'NULL';
                } else {
                    $escaped = $conn->real_escape_string((string)$row[$j]);
                    $sql .= "'" . $escaped . "'";
                }
                if ($j < ($num_fields - 1)) {
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
function getAttendanceLogRecords($conn, $month = '', $from = '', $to = '', $branch = '', $staff_id = '', $search = '') {
    $where = [];
    $params = [];
    $types = '';

    // Staff filter (individual staff_id or search string)
    if (!empty($staff_id) && $staff_id !== 'all') {
        $where[] = "a.staff_id = ?";
        $params[] = $staff_id;
        $types .= 's';
    } elseif (!empty($search)) {
        $where[] = "(s.full_name LIKE ? OR a.staff_id LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $types .= 'ss';
    }

    // Date range filter
    if (!empty($from) && !empty($to)) {
        $where[] = "DATE(a.clock_in) BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
        $types .= 'ss';
    } elseif (!empty($from)) {
        $where[] = "DATE(a.clock_in) >= ?";
        $params[] = $from;
        $types .= 's';
    } elseif (!empty($to)) {
        $where[] = "DATE(a.clock_in) <= ?";
        $params[] = $to;
        $types .= 's';
    } elseif (!empty($month) && $month !== 'all') {
        $where[] = "DATE_FORMAT(a.clock_in, '%Y-%m') = ?";
        $params[] = $month;
        $types .= 's';
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
            if ($src === 'DEVICE') $src = 'Fingerprint (Device)';
            elseif ($src === 'FINGERPRINT') $src = 'WebAuthn Thumbprint';
            elseif ($src === 'CARD') $src = 'E-Card / RFID Smart Badge';
            elseif ($src === 'MOBILE') $src = 'Face Recognition';

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
// HELPER: Pure PHP ZIP Archive Generator (Zero extension dependency)
// -------------------------------------------------------------
function createPurePhpZip(array $files): string {
    $datasec = [];
    $ctrl_dir = [];
    $old_offset = 0;

    foreach ($files as $name => $data) {
        $name = str_replace('\\', '/', $name);
        $dtime = dechex(date('Y') - 1980 << 25 | date('m') << 21 | date('d') << 16 | date('H') << 11 | date('i') << 5 | date('s') >> 1);
        $hexdtime = pack('V', hexdec($dtime));

        $unc_len = strlen($data);
        $crc = crc32($data);
        $useDeflate = function_exists('gzdeflate');
        $zdata = $useDeflate ? gzdeflate($data) : $data;
        $c_len = strlen($zdata);
        $method = $useDeflate ? "\x08\x00" : "\x00\x00";

        // Local file header
        $fr = "\x50\x4b\x03\x04\x14\x00\x00\x00" . $method . $hexdtime
            . pack('V', $crc)
            . pack('V', $c_len)
            . pack('V', $unc_len)
            . pack('v', strlen($name))
            . pack('v', 0)
            . $name
            . $zdata;
        $datasec[] = $fr;

        // Central directory entry
        $cdrec = "\x50\x4b\x01\x02\x00\x00\x14\x00\x00\x00" . $method . $hexdtime
            . pack('V', $crc)
            . pack('V', $c_len)
            . pack('V', $unc_len)
            . pack('v', strlen($name))
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 32)
            . pack('V', $old_offset)
            . $name;
        $ctrl_dir[] = $cdrec;
        $old_offset += strlen($fr);
    }

    $data = implode('', $datasec);
    $ctrldir = implode('', $ctrl_dir);

    return $data
        . $ctrldir
        . "\x50\x4b\x05\x06\x00\x00\x00\x00"
        . pack('v', count($ctrl_dir))
        . pack('v', count($ctrl_dir))
        . pack('V', strlen($ctrldir))
        . pack('V', strlen($data))
        . "\x00\x00";
}

// -------------------------------------------------------------
// HELPER: Create ZIP Archive in memory (Supports Vercel serverless)
// -------------------------------------------------------------
function createZipArchive($files) {
    if (class_exists('ZipArchive')) {
        $tempZip = tempnam(sys_get_temp_dir(), 'att_zip_');
        $zip = new ZipArchive();
        if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();
            $data = file_get_contents($tempZip);
            @unlink($tempZip);
            if ($data !== false && strlen($data) > 0) {
                return $data;
            }
        }
    }
    // Universal pure-PHP fallback (works on Vercel without ext-zip)
    return createPurePhpZip($files);
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
    $month    = trim($_GET['month'] ?? '');
    $from     = trim($_GET['from'] ?? '');
    $to       = trim($_GET['to'] ?? '');
    $branch   = trim($_GET['branch'] ?? '');
    $staff_id = trim($_GET['staff_id'] ?? '');
    $search   = trim($_GET['search'] ?? '');
    $isZip    = (!empty($_GET['zip']) && $_GET['zip'] == '1');

    $records = getAttendanceLogRecords($conn, $month, $from, $to, $branch, $staff_id, $search);
    $csvData = generateCsvFromRecords($records, 'No attendance records found for this period.');

    // Build smart dynamic filename
    $staffLabel = '';
    if (!empty($staff_id) || !empty($search)) {
        $resolvedName = '';
        if (!empty($records) && !empty($records[0]['Full Name']) && $records[0]['Full Name'] !== 'Unknown Staff') {
            $resolvedName = $records[0]['Full Name'];
        } elseif (!empty($staff_id)) {
            $stQ = $conn->prepare("SELECT full_name FROM staff WHERE staff_id = ? LIMIT 1");
            if ($stQ) {
                $stQ->bind_param("s", $staff_id);
                $stQ->execute();
                $stRow = $stQ->get_result()->fetch_assoc();
                if ($stRow) $resolvedName = $stRow['full_name'];
                $stQ->close();
            }
        }
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $resolvedName ?: ($staff_id ?: $search));
        $staffLabel = $cleanName . "_";
    }

    if (!empty($from) && !empty($to)) {
        $dateLabel = ($from === $to) ? $from : "{$from}_to_{$to}";
    } elseif (!empty($from)) {
        $dateLabel = "from_{$from}";
    } elseif (!empty($to)) {
        $dateLabel = "to_{$to}";
    } elseif (!empty($month) && $month !== 'all') {
        $dateLabel = $month;
    } else {
        $dateLabel = ($month === 'all') ? 'all_time' : date('Y-m-d');
    }

    $fileBase = "attendance_{$staffLabel}{$dateLabel}";

    if ($isZip) {
        $zipData = createZipArchive([
            "{$fileBase}.csv" => $csvData
        ]);
        if ($zipData !== false && strlen($zipData) > 0) {
            $zipFilename = "{$fileBase}.zip";
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
            header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
            header('Pragma: public');
            echo $zipData;
            exit;
        }
    }
    $filename = "{$fileBase}.csv";
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
    @ini_set('memory_limit', '512M');
    @set_time_limit(180);

    // Clear all output buffers from router.php so clean headers and bytes stream directly
    while (ob_get_level()) {
        ob_end_clean();
    }

    $sql_dump = dumpDatabase($conn);
    $filename_base = "attendance_backup_" . date("Y-m-d_H-i");
    $size = strlen($sql_dump);

    // If format=zip is requested or file exceeds 2MB, zip it
    if ((isset($_GET['format']) && $_GET['format'] === 'zip') || $size > (2 * 1024 * 1024)) {
        $zipData = createZipArchive([
            "{$filename_base}.sql" => $sql_dump
        ]);

        if ($zipData !== false && strlen($zipData) > 0) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename_base . '.sql.zip"');
            header('Content-Length: ' . strlen($zipData));
            header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
            header('Pragma: public');
            echo $zipData;
            exit;
        }
    }

    // Direct plain .sql download (clean, under 200KB, downloads in 0.2s!)
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename_base . '.sql"');
    header('Content-Length: ' . $size);
    header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
    header('Pragma: public');
    echo $sql_dump;
    exit;
}

// -------------------------------------------------------------
// ACTION: View & Copy SQL Dump (One-click import into TiDB Cloud)
// -------------------------------------------------------------
if ($action === 'view_sql') {
    @ini_set('memory_limit', '512M');
    @set_time_limit(180);

    while (ob_get_level()) {
        ob_end_clean();
    }

    $sql_dump = dumpDatabase($conn);
    $sizeKb = round(strlen($sql_dump) / 1024, 1);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database SQL Backup for TiDB Cloud</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 24px; }
            .container { max-width: 900px; margin: 0 auto; background: #1e293b; border-radius: 16px; padding: 28px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
            h2 { margin-top: 0; color: #38bdf8; display: flex; align-items: center; gap: 10px; font-size: 22px; }
            p { color: #94a3b8; font-size: 14px; line-height: 1.6; }
            .btn-group { display: flex; gap: 12px; margin: 20px 0; flex-wrap: wrap; }
            .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; border: none; transition: 0.2s; }
            .btn-primary { background: #3b82f6; color: white; }
            .btn-primary:hover { background: #2563eb; }
            .btn-success { background: #10b981; color: white; }
            .btn-success:hover { background: #059669; }
            .btn-secondary { background: #334155; color: #cbd5e1; }
            .btn-secondary:hover { background: #475569; }
            textarea { width: 100%; height: 440px; background: #0f172a; color: #a5f3fc; border: 1px solid #334155; border-radius: 10px; padding: 16px; font-family: 'JetBrains Mono', monospace; font-size: 12px; line-height: 1.5; resize: vertical; box-sizing: border-box; }
            .steps { background: #0f172a; border-left: 4px solid #38bdf8; padding: 14px 18px; border-radius: 0 8px 8px 0; margin-bottom: 20px; font-size: 13px; color: #cbd5e1; }
            .steps ol { margin: 6px 0 0 18px; padding: 0; }
            .steps li { margin-bottom: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2><span>📦</span> Database Backup for TiDB Cloud Migration</h2>
            <div class="steps">
                <strong>How to import into TiDB Cloud:</strong>
                <ol>
                    <li>Click <strong>"📋 Copy All SQL"</strong> below (or <strong>"💾 Download .sql File"</strong>).</li>
                    <li>Go to your <strong>TiDB Cloud Console &rarr; SQL Editor</strong>.</li>
                    <li>Paste the SQL script into the editor and click <strong>"Run"</strong>. That's it!</li>
                </ol>
            </div>
            <div class="btn-group">
                <button class="btn btn-success" onclick="copySql()">📋 Copy All SQL (<?php echo $sizeKb; ?> KB)</button>
                <a href="backup.php?action=download" class="btn btn-primary">💾 Download .sql File</a>
                <a href="settings.php" class="btn btn-secondary">← Back to Settings</a>
            </div>
            <textarea id="sqlText" readonly><?php echo htmlspecialchars($sql_dump); ?></textarea>
        </div>
        <script>
            function copySql() {
                const ta = document.getElementById('sqlText');
                ta.select();
                navigator.clipboard.writeText(ta.value).then(() => {
                    alert('✅ Database SQL copied to clipboard! You can now paste it directly into TiDB Cloud SQL Editor.');
                }).catch(() => {
                    document.execCommand('copy');
                    alert('✅ Database SQL copied to clipboard!');
                });
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// -------------------------------------------------------------
// ACTION: Email SQL Database Backup
// -------------------------------------------------------------
if ($action === 'email') {
    @ini_set('memory_limit', '512M');
    @set_time_limit(180);

    $sql_dump = dumpDatabase($conn);
    $filename_base = "attendance_backup_" . date("Y-m-d_H-i");
    $size = strlen($sql_dump);

    $subject = "Database Backup - " . date("M d, Y");
    $html = "<h3>Database Backup</h3><p>Attached is your latest attendance database backup (.sql).</p>";

    // Compress to zip for fast and reliable email delivery
    $zipData = createZipArchive([
        "{$filename_base}.sql" => $sql_dump
    ]);
    if ($zipData !== false && strlen($zipData) > 0) {
        $attachments = [
            ['name' => "{$filename_base}.sql.zip", 'content' => $zipData]
        ];
    } else {
        $attachments = [
            ['name' => "{$filename_base}.sql.txt", 'content' => $sql_dump]
        ];
    }

    $res = sendBrevoEmail($conn, $subject, $html, $attachments);
    if ($res['success']) {
        echo "<!DOCTYPE html><html><body><script>alert('✅ Database backup sent successfully to {$res['email']}'); window.location.href='settings.php';</script></body></html>";
    } else {
        $safeResp = json_encode($res['error']);
        echo "<!DOCTYPE html><html><body><script>alert('❌ Failed to send database backup.\\n\\nDetails: ' + " . $safeResp . "); window.location.href='settings.php';</script></body></html>";
    }
    exit;
}
?>
