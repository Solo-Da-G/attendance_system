<?php
include(__DIR__ . "/includes/config.php");

// Ensure auto-backup columns exist in `admin` table
if (!db_has_column($conn, 'admin', 'auto_backup_freq')) {
    $conn->query("ALTER TABLE `admin` ADD COLUMN `auto_backup_freq` VARCHAR(20) DEFAULT 'never'");
}
if (!db_has_column($conn, 'admin', 'last_backup_time')) {
    $conn->query("ALTER TABLE `admin` ADD COLUMN `last_backup_time` DATETIME NULL");
}

if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized");
}

$action = $_GET['action'] ?? '';

// Check auto-backup frequency first
if ($action === 'auto') {
    $stmt = $conn->prepare("SELECT id, auto_backup_freq, last_backup_time FROM admin WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $adminRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$adminRow || $adminRow['auto_backup_freq'] === 'never') {
        die("Auto backup disabled.");
    }
    
    $freq = $adminRow['auto_backup_freq'];
    $last = $adminRow['last_backup_time'] ? strtotime($adminRow['last_backup_time']) : 0;
    
    $intervals = [
        '24_hours' => 24 * 3600,
        '7_days' => 7 * 24 * 3600,
        '14_days' => 14 * 24 * 3600,
        '30_days' => 30 * 24 * 3600,
        '90_days' => 90 * 24 * 3600,
        '180_days' => 180 * 24 * 3600,
        '365_days' => 365 * 24 * 3600,
    ];
    
    $required_interval = $intervals[$freq] ?? null;
    if (!$required_interval || (time() - $last) < $required_interval) {
        die("Not time yet.");
    }
}

// Proceed to dump database
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
// HELPER: Fetch detailed employee information with metrics
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
function generateEmployeesCsv($records) {
    $out = fopen('php://temp', 'r+');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Microsoft Excel auto-detection
    if (!empty($records)) {
        fputcsv($out, array_keys($records[0]));
        foreach ($records as $r) {
            fputcsv($out, array_values($r));
        }
    } else {
        fputcsv($out, ['Message']);
        fputcsv($out, ['No employee records found in database.']);
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    return $csv;
}

// -------------------------------------------------------------
// HELPER: Generate XML Spreadsheet 2003 (.xls) for Microsoft Excel
// -------------------------------------------------------------
function generateEmployeesXmlExcel($records, $sheetName = "Employees") {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
    $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
    $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    $xml .= ' <Styles>' . "\n";
    $xml .= '  <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Color="#1e293b"/></Style>' . "\n";
    $xml .= '  <Style ss:ID="Header"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#10439F" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0c3276"/></Borders></Style>' . "\n";
    $xml .= '  <Style ss:ID="Text"><NumberFormat ss:Format="@"/></Style>' . "\n";
    $xml .= '  <Style ss:ID="Active"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#059669"/><Alignment ss:Horizontal="Center"/></Style>' . "\n";
    $xml .= '  <Style ss:ID="Archived"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#dc2626"/><Alignment ss:Horizontal="Center"/></Style>' . "\n";
    $xml .= ' </Styles>' . "\n";
    $xml .= ' <Worksheet ss:Name="' . htmlspecialchars($sheetName, ENT_QUOTES | ENT_XML1, 'UTF-8') . '">' . "\n";
    $xml .= '  <Table>' . "\n";
    
    if (!empty($records)) {
        $headers = array_keys($records[0]);
        $xml .= '   <Row ss:Height="26" ss:StyleID="Header">' . "\n";
        foreach ($headers as $h) {
            $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars((string)$h, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>' . "\n";
        }
        $xml .= '   </Row>' . "\n";
        
        foreach ($records as $r) {
            $xml .= '   <Row ss:Height="22">' . "\n";
            foreach ($r as $key => $val) {
                $style = ' ss:StyleID="Text"';
                if ($key === 'Employment Status') {
                    $style = (strpos((string)$val, 'Active') !== false) ? ' ss:StyleID="Active"' : ' ss:StyleID="Archived"';
                }
                $xml .= '    <Cell' . $style . '><Data ss:Type="String">' . htmlspecialchars((string)$val, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>' . "\n";
            }
            $xml .= '   </Row>' . "\n";
        }
    } else {
        $xml .= '   <Row ss:Height="26" ss:StyleID="Header"><Cell><Data ss:Type="String">Message</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="20"><Cell ss:StyleID="Text"><Data ss:Type="String">No employee records found in database.</Data></Cell></Row>' . "\n";
    }
    
    $xml .= '  </Table>' . "\n";
    $xml .= ' </Worksheet>' . "\n";
    $xml .= '</Workbook>' . "\n";
    return $xml;
}

// -------------------------------------------------------------
// ACTION: Download Employees Excel / CSV
// -------------------------------------------------------------
if ($action === 'employees_excel' || $action === 'employees_download') {
    $records = getEmployeeRecords($conn);
    $format = strtolower($_GET['format'] ?? 'csv');

    if ($format === 'xls') {
        $output = generateEmployeesXmlExcel($records);
        $filename = "attendance_employees_backup_" . date("Y-m-d_H-i") . ".xls";
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        $output = generateEmployeesCsv($records);
        $filename = "attendance_employees_backup_" . date("Y-m-d_H-i") . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }

    header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
    header('Pragma: public');
    echo $output;
    exit;
}

// -------------------------------------------------------------
// ACTION: Email Employees Excel / CSV Backup
// -------------------------------------------------------------
if ($action === 'email_employees') {
    $apiKey = getenv('BREVO_API_KEY') ?: $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? '';
    if ($apiKey === '') {
        die("<!DOCTYPE html><html><body><script>alert('Brevo API not configured in Vercel env vars.'); window.location.href='dashboard.php';</script></body></html>");
    }

    $fromEmail = getenv('BREVO_FROM_EMAIL') ?: $_ENV['BREVO_FROM_EMAIL'] ?? $_SERVER['BREVO_FROM_EMAIL'] ?? 'no-reply@attendance.system';
    $fromName  = getenv('BREVO_FROM_NAME') ?: $_ENV['BREVO_FROM_NAME'] ?? $_SERVER['BREVO_FROM_NAME'] ?? 'Attendance System';

    // Get admin email
    $stmt = $conn->prepare("SELECT email FROM admin WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $adminEmail = $stmt->get_result()->fetch_assoc()['email'] ?? 'admin@localhost';
    $stmt->close();

    $records   = getEmployeeRecords($conn);
    $csvData   = generateEmployeesCsv($records);
    $filename  = "attendance_employees_backup_" . date("Y-m-d_H-i") . ".csv";
    $count     = count($records);

    $postData = [
        'sender'      => ['name' => $fromName, 'email' => $fromEmail],
        'to'          => [['email' => $adminEmail, 'name' => 'Admin']],
        'subject'     => 'Employee Information Backup (Excel/CSV) - ' . date("M d, Y"),
        'htmlContent' => '<h3>Employee Information Backup</h3><p>Attached is the complete employee information spreadsheet backup containing <strong>' . $count . ' staff records</strong> with contact details, branches, departments, biometric enrollment status, and attendance summaries.</p><p><small>Generated on ' . date("M d, Y h:i A") . '</small></p>',
        'attachment'  => [
            [
                'name'    => $filename,
                'content' => base64_encode($csvData)
            ]
        ]
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
        CURLOPT_TIMEOUT => 20,
    ]);

    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($code >= 200 && $code < 300) {
        echo "<!DOCTYPE html><html><body><script>alert('✅ Employee Excel backup sent successfully to $adminEmail ($count staff records)'); window.location.href='dashboard.php';</script></body></html>";
    } else {
        $safeResp = json_encode(substr((string)$resp, 0, 150));
        echo "<!DOCTYPE html><html><body><script>alert('❌ Failed to send employee backup. Check Brevo config.\\n\\nStatus: " . $code . "\\nResponse: ' + " . $safeResp . "); window.location.href='dashboard.php';</script></body></html>";
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

if ($action === 'email' || $action === 'auto') {
    // Send email using Brevo
    $apiKey = getenv('BREVO_API_KEY') ?: $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? '';
    if ($apiKey === '') {
        die("<!DOCTYPE html><html><body><script>alert('Brevo API not configured in Vercel env vars.'); window.location.href='dashboard.php';</script></body></html>");
    }
    
    $fromEmail = getenv('BREVO_FROM_EMAIL') ?: $_ENV['BREVO_FROM_EMAIL'] ?? $_SERVER['BREVO_FROM_EMAIL'] ?? 'no-reply@attendance.system';
    $fromName  = getenv('BREVO_FROM_NAME') ?: $_ENV['BREVO_FROM_NAME'] ?? $_SERVER['BREVO_FROM_NAME'] ?? 'Attendance System';
    
    // Get admin email
    $stmt = $conn->prepare("SELECT email FROM admin WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $adminEmail = $stmt->get_result()->fetch_assoc()['email'] ?? 'admin@localhost';
    $stmt->close();
    
    $postData = [
        'sender'      => ['name' => $fromName, 'email' => $fromEmail],
        'to'          => [['email' => $adminEmail, 'name' => 'Admin']],
        'subject'     => 'Database Backup - ' . date("M d, Y"),
        'htmlContent' => '<h3>Database Backup</h3><p>Attached is your latest attendance database backup.</p>',
        'attachment'  => [
            [
                'name'    => $filename . '.txt',
                'content' => base64_encode($sql_dump)
            ]
        ]
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
        CURLOPT_TIMEOUT => 20,
    ]);
    
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($action === 'auto' && $code >= 200 && $code < 300) {
        $conn->query("UPDATE admin SET last_backup_time = NOW() WHERE id = " . (int)$_SESSION['admin_id']);
        die("Auto backup sent.");
    }
    
    if ($action === 'email') {
        if ($code >= 200 && $code < 300) {
            echo "<!DOCTYPE html><html><body><script>alert('✅ Backup sent successfully to $adminEmail'); window.location.href='dashboard.php';</script></body></html>";
        } else {
            $safeResp = json_encode(substr((string)$resp, 0, 150));
            echo "<!DOCTYPE html><html><body><script>alert('❌ Failed to send backup. Check Brevo config.\\n\\nStatus: " . $code . "\\nResponse: ' + " . $safeResp . "); window.location.href='dashboard.php';</script></body></html>";
        }
    }
}
?>
