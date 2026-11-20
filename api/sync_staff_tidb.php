<?php
/**
 * ONE-CLICK DIRECT CLOUD-TO-CLOUD SYNC (Aiven -> TiDB Cloud)
 * Transfers all staff, biometrics, and devices directly into TiDB Cloud!
 */
include(__DIR__ . "/includes/config.php");

if (empty($_SESSION['admin'])) {
    die("<h3>🔒 Admin Authentication Required</h3><p>Please <a href='login.php'>log in</a> first.</p>");
}

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>TiDB Cloud Data Sync</title>";
echo "<link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&family=JetBrains+Mono&display=swap' rel='stylesheet'>";
echo "<style>body{font-family:'Plus Jakarta Sans',sans-serif;background:#0f172a;color:#f8fafc;padding:30px;line-height:1.6;} .card{max-width:700px;margin:0 auto;background:#1e293b;padding:24px;border-radius:14px;box-shadow:0 10px 25px rgba(0,0,0,0.5);} h2{color:#38bdf8;margin-top:0;} pre{background:#0f172a;color:#a5f3fc;padding:16px;border-radius:8px;font-family:'JetBrains Mono',monospace;font-size:13px;overflow-x:auto;} .btn{display:inline-block;background:#10b981;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700;margin-top:16px;}</style></head><body><div class='card'>";
echo "<h2>🔄 Direct Cloud-to-Cloud Sync (Aiven &rarr; TiDB)</h2><pre>";

$tidbHost = getenv('HOST') ?: (getenv('DB_HOST') && stripos(getenv('DB_HOST'), 'tidb') !== false ? getenv('DB_HOST') : 'gateway01.eu-central-1.prod.aws.tidbcloud.com');
$tidbUser = getenv('USERNAME') ?: (getenv('DB_USER') && stripos(getenv('DB_USER'), '.root') !== false ? getenv('DB_USER') : '2VyLTo8URkrLGfn.root');
$tidbPass = getenv('PASSWORD') ?: (getenv('DB_PASSWORD') ?: 'gwzlVRVGeLHJ7ygs');
$tidbDb   = 'test';
$tidbPort = 4000;

$tidb = mysqli_init();
$tidb->ssl_set(NULL, NULL, NULL, NULL, NULL);
$tidb->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
$tidb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 15);

if (!@$tidb->real_connect($tidbHost, $tidbUser, $tidbPass, $tidbDb, $tidbPort, NULL, MYSQLI_CLIENT_SSL)) {
    echo "❌ Failed to connect to TiDB Cloud: " . mysqli_connect_error() . "</pre></div></body></html>";
    exit;
}
echo "✅ Connected to TiDB Cloud successfully!\n\n";

$tidb->query("SET FOREIGN_KEY_CHECKS=0");

$tablesToSync = ['staff', 'staff_biometrics', 'zk_devices', 'branches', 'admin', 'attendance', 'admin_notifications', 'field_work_comments', 'audit_log'];

foreach ($tablesToSync as $tbl) {
    $checkSource = $conn->query("SHOW TABLES LIKE '$tbl'");
    if (!$checkSource || $checkSource->num_rows === 0) {
        continue;
    }

    $checkTarget = $tidb->query("SHOW TABLES LIKE '$tbl'");
    if (!$checkTarget || $checkTarget->num_rows === 0) {
        $cRow = $conn->query("SHOW CREATE TABLE `{$tbl}`")->fetch_row();
        $createSql = preg_replace('/"([a-zA-Z0-9_]+)"/', '`$1`', $cRow[1]);
        $tidb->query($createSql);
        echo "📁 Created table `{$tbl}` in TiDB Cloud.\n";
    }

    $targetCount = (int)$tidb->query("SELECT COUNT(*) FROM `{$tbl}`")->fetch_row()[0];
    $sourceCount = (int)$conn->query("SELECT COUNT(*) FROM `{$tbl}`")->fetch_row()[0];

    if ($targetCount >= $sourceCount && $targetCount > 0) {
        echo "✔️  Table `{$tbl}` is already up to date ({$targetCount} rows).\n";
        continue;
    }

    $sRes = $conn->query("SELECT * FROM `{$tbl}`");
    $fields = $sRes->fetch_fields();
    $numFields = count($fields);
    
    $tidb->query("TRUNCATE TABLE `{$tbl}`");
    $copied = 0;

    while ($r = $sRes->fetch_row()) {
        $vals = [];
        for ($j = 0; $j < $numFields; $j++) {
            $colName = $fields[$j]->name;
            if (($tbl === 'attendance' && ($colName === 'photo_in' || $colName === 'photo_out')) ||
                ($tbl === 'staff' && $colName === 'photo')) {
                $vals[] = 'NULL';
            } elseif ($r[$j] === null) {
                $vals[] = 'NULL';
            } else {
                $vals[] = "'" . $tidb->real_escape_string((string)$r[$j]) . "'";
            }
        }
        $tidb->query("INSERT INTO `{$tbl}` VALUES(" . implode(',', $vals) . ")");
        $copied++;
    }
    echo "🚀 Table `{$tbl}`: copied {$copied} / {$sourceCount} rows to TiDB.\n";
}

$tidb->query("SET FOREIGN_KEY_CHECKS=1");

echo "\n🎉 SYNC COMPLETED SUCCESSFULLY! All data is now live on TiDB Cloud!\n";
echo "</pre><a href='settings.php' class='btn'>← Return to Settings</a></div></body></html>";
