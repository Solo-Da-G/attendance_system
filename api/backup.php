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

$sql_dump = dumpDatabase($conn);
$filename = "attendance_backup_" . date("Y-m-d_H-i") . ".sql";

if ($action === 'download') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $sql_dump;
    exit;
}

if ($action === 'email' || $action === 'auto') {
    // Send email using Brevo
    $apiKey = getenv('BREVO_API_KEY') ?: $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? '';
    if ($apiKey === '') {
        die("<script>alert('Brevo API not configured in Vercel env vars.'); window.location.href='dashboard.php';</script>");
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
                'name'    => $filename,
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
            echo "<script>alert('✅ Backup sent successfully to $adminEmail'); window.location.href='dashboard.php';</script>";
        } else {
            $errDetails = "Status: " . $code . "\\nResponse: " . addslashes(substr((string)$resp, 0, 150));
            echo "<script>alert('❌ Failed to send backup. Check Brevo config.\\n\\nDetails: $errDetails'); window.location.href='dashboard.php';</script>";
        }
    }
}
?>
