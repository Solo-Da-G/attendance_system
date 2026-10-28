<?php
include(__DIR__ . "/includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("<h2 style='color:red;text-align:center;margin-top:100px;font-family:sans-serif;'>Access Denied.</h2>");
}

// Ensure auto-backup columns exist
if (!db_has_column($conn, 'admin', 'auto_backup_freq')) {
    $conn->query("ALTER TABLE `admin` ADD COLUMN `auto_backup_freq` VARCHAR(20) DEFAULT 'never'");
}
if (!db_has_column($conn, 'admin', 'auto_backup_type')) {
    $conn->query("ALTER TABLE `admin` ADD COLUMN `auto_backup_type` VARCHAR(30) DEFAULT 'both'");
}
if (!db_has_column($conn, 'admin', 'last_backup_time')) {
    $conn->query("ALTER TABLE `admin` ADD COLUMN `last_backup_time` DATETIME NULL");
}

$success = "";
$error = "";

/* UPDATE SETTINGS */
if (isset($_POST['update_settings'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $admin_id = $_SESSION['admin_id'];

    if (empty($username)) {
        $error = "Username cannot be empty";
    } else {
        $auto_backup = $_POST['auto_backup_freq'] ?? 'never';
        $auto_type   = $_POST['auto_backup_type'] ?? 'both';

        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "UPDATE admin SET username = ?, password = ?, auto_backup_freq = ?, auto_backup_type = ? WHERE id = ?"
            );
            $stmt->bind_param("ssssi", $username, $hashedPassword, $auto_backup, $auto_type, $admin_id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE admin SET username = ?, auto_backup_freq = ?, auto_backup_type = ? WHERE id = ?"
            );
            $stmt->bind_param("sssi", $username, $auto_backup, $auto_type, $admin_id);
        }

        if ($stmt->execute()) {
            $success = "Settings updated successfully";
            $_SESSION['admin'] = $username;
        } else {
            $error = "Failed to update settings";
        }
        $stmt->close();
    }
}

/* FETCH CURRENT ADMIN */
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT username, auto_backup_freq, auto_backup_type, email FROM `admin` WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
if (!isset($admin['auto_backup_freq'])) $admin['auto_backup_freq'] = 'never';
if (!isset($admin['auto_backup_type'])) $admin['auto_backup_type'] = 'both';
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0f172a">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="TDS Attendance">
  <link rel="manifest" href="/manifest.json">
  <link rel="apple-touch-icon" href="/asset/img/tds_logo.png">
<title>Admin Settings</title>
<link rel="stylesheet" href="/asset/css/style.css">
<style>
    .backup-box {
        border-radius: 14px;
        padding: 20px;
        margin-top: 22px;
        transition: all .2s ease;
    }
    .backup-btn {
        padding: 10px 16px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: white;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .backup-btn:hover {
        transform: translateY(-1px);
    }
    .freq-prompt-box {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        padding: 12px 14px;
        margin-top: 12px;
        font-size: 13px;
        color: #334155;
    }
</style>
</head>
<body class="app-page">

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
    <div class="settings-box" style="max-width:800px; margin: 0 auto; background:var(--surface); padding:30px; border-radius:var(--radius-lg); box-shadow:var(--shadow);">
        <h2 style="margin-bottom:20px;">⚙️ Admin Settings</h2>

        <?php if ($success) { ?>
            <p class="badge badge-success" style="display:block; margin-bottom:15px; text-align:center; padding:10px;"><?php echo $success; ?></p>
        <?php } ?>

        <?php if ($error) { ?>
            <p class="badge badge-danger" style="display:block; margin-bottom:15px; text-align:center; padding:10px;"><?php echo $error; ?></p>
        <?php } ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" required>

            <label style="margin-top:15px; display:block;">New Password (leave blank to keep current)</label>
            <input type="password" name="password" placeholder="Enter new password">

            <!-- ── SECTION 1: AUTOMATED EMAIL BACKUPS ── -->
            <div class="backup-box" style="background: rgba(139, 92, 246, 0.08); border: 1.5px solid rgba(139, 92, 246, 0.3);">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                    <h4 style="margin: 0; color: #6d28d9; font-size:16px; display:flex; align-items:center; gap:8px;">
                        <span>🔄</span> Automated Scheduled Backups to Email
                    </h4>
                    <span style="font-size:11px; font-weight:700; background:#ede9fe; color:#6d28d9; padding:3px 8px; border-radius:6px;">Brevo Email Integration</span>
                </div>
                <p style="font-size: 13px; color: #4b5563; margin: 0 0 14px; line-height: 1.4;">
                    Configure automated backups to be sent directly to your administrator email address (<code><?php echo htmlspecialchars($admin['email'] ?? 'Admin Email'); ?></code>) in space-saving compressed format.
                </p>

                <!-- What to backup -->
                <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">1. Select What to Receive in Your Email:</label>
                <select name="auto_backup_type" id="autoBackupType" style="width: 100%; padding: 11px; border-radius: 8px; border: 1px solid var(--border); font-size: 14px; background: white; margin-bottom: 14px;">
                    <option value="both" <?php echo ($admin['auto_backup_type'] === 'both') ? 'selected' : ''; ?>>📦 Both Database (.sql) &amp; Attendance Records (.csv) in ZIP (Recommended)</option>
                    <option value="excel" <?php echo ($admin['auto_backup_type'] === 'excel') ? 'selected' : ''; ?>>📊 Attendance &amp; Staff Records (.csv in ZIP)</option>
                    <option value="sql" <?php echo ($admin['auto_backup_type'] === 'sql') ? 'selected' : ''; ?>>🗄️ Database Backup (.sql only)</option>
                </select>

                <!-- Frequency -->
                <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">2. Select Automated Schedule (Days, Weeks, or Months):</label>
                <select name="auto_backup_freq" id="autoBackupFreq" onchange="updateFreqNotice(this.value)" style="width: 100%; padding: 11px; border-radius: 8px; border: 1px solid var(--border); font-size: 14px; background: white;">
                    <option value="never" <?php echo ($admin['auto_backup_freq'] === 'never') ? 'selected' : ''; ?>>🚫 Disabled (Manual download only)</option>
                    <option value="24_hours" <?php echo ($admin['auto_backup_freq'] === '24_hours') ? 'selected' : ''; ?>>📅 Daily (Every 24 Hours)</option>
                    <option value="7_days" <?php echo ($admin['auto_backup_freq'] === '7_days') ? 'selected' : ''; ?>>📆 Weekly (Every 7 Days)</option>
                    <option value="14_days" <?php echo ($admin['auto_backup_freq'] === '14_days') ? 'selected' : ''; ?>>📆 Bi-Weekly (Every 14 Days)</option>
                    <option value="30_days" <?php echo ($admin['auto_backup_freq'] === '30_days') ? 'selected' : ''; ?>>🗓️ Monthly (Every 30 Days)</option>
                    <option value="90_days" <?php echo ($admin['auto_backup_freq'] === '90_days') ? 'selected' : ''; ?>>🗓️ Every 3 Months (Quarterly)</option>
                    <option value="180_days" <?php echo ($admin['auto_backup_freq'] === '180_days') ? 'selected' : ''; ?>>🗓️ Every 6 Months</option>
                    <option value="365_days" <?php echo ($admin['auto_backup_freq'] === '365_days') ? 'selected' : ''; ?>>🗓️ Every 1 Year (Annually)</option>
                </select>

                <div class="freq-prompt-box" id="freqPromptNotice" style="<?php echo ($admin['auto_backup_freq'] === 'never') ? 'display:none;' : ''; ?>">
                    <span id="freqNoticeText">
                        ✅ The system will automatically compress and email the latest records to <strong><?php echo htmlspecialchars($admin['email'] ?? 'admin'); ?></strong>.
                    </span>
                </div>

                <!-- Instant Manual SQL actions -->
                <div style="margin-top: 16px; pt-top: 12px; border-top: 1px dashed rgba(139, 92, 246, 0.3); padding-top: 14px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="backup.php?action=download" target="_blank" class="backup-btn" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3);">
                        <span>💾</span> Manual Download Database (.sql)
                    </a>
                    <a href="backup.php?action=email" class="backup-btn" style="background: linear-gradient(135deg, #0284c7, #0369a1); box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3);" onclick="return confirm('Send the database SQL backup to your email now?');">
                        <span>✉️</span> Email Database (.sql) Now
                    </a>
                </div>
            </div>

            <!-- ── SECTION 2: ATTENDANCE & EMPLOYEE EXCEL/CSV EXPORT ── -->
            <div class="backup-box" style="background: rgba(16, 185, 129, 0.08); border: 1.5px solid rgba(16, 185, 129, 0.3);">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                    <h4 style="margin: 0; color: #047857; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <span>📊</span> Download Attendance &amp; Staff Records (Excel / CSV)
                    </h4>
                    <span style="font-size:11px; font-weight:700; background:#d1fae5; color:#065f46; padding:3px 8px; border-radius:6px;">Searchable &amp; Sortable</span>
                </div>
                <p style="font-size: 13px; color: #4b5563; margin: 0 0 14px; line-height: 1.4;">
                    Download detailed clock-in/out records for all employees. Open in Microsoft Excel or Google Sheets to easily sort, search, and filter by any employee, date, branch, or total hours worked.
                </p>

                <!-- Month Picker & All Time Option -->
                <div style="background: white; border: 1px solid #d1fae5; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                    <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                        <div style="flex:1; min-width:200px;">
                            <label style="display:block; font-size:12px; font-weight:700; color:#065f46; margin-bottom:4px; text-transform:uppercase;">Select Month:</label>
                            <input type="month" id="attMonthSelect" value="<?php echo date('Y-m'); ?>" onchange="refreshExportUrls()" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; font-size:14px; font-weight:600;">
                        </div>
                        <div style="flex:1; min-width:220px; display:flex; align-items:center; gap:8px; padding-top:18px;">
                            <label style="font-size:13px; font-weight:600; color:#334155; cursor:pointer; display:flex; align-items:center; gap:8px;">
                                <input type="checkbox" id="attAllTimeCheck" onchange="refreshExportUrls()" style="width:18px; height:18px; cursor:pointer;">
                                <span>Download All Months (All-Time History)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Export & Email Buttons -->
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a id="btnAttCsv" href="backup.php?action=attendance_excel&month=<?php echo date('Y-m'); ?>" target="_blank" class="backup-btn" style="background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">
                        <span>📊</span> Download Attendance Log (.csv)
                    </a>
                    <a id="btnAttZip" href="backup.php?action=attendance_excel&month=<?php echo date('Y-m'); ?>&zip=1" target="_blank" class="backup-btn" style="background: linear-gradient(135deg, #059669, #047857); box-shadow: 0 4px 10px rgba(5, 150, 105, 0.3);">
                        <span>📦</span> Download Compressed (.zip)
                    </a>
                    <a id="btnAttEmail" href="backup.php?action=email_attendance&month=<?php echo date('Y-m'); ?>" class="backup-btn" style="background: linear-gradient(135deg, #0284c7, #0369a1); box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3);" onclick="return confirm('Email compressed attendance records to your inbox now?');">
                        <span>✉️</span> Email Records (.zip)
                    </a>
                    <a href="backup.php?action=employees_excel" target="_blank" class="backup-btn" style="background: linear-gradient(135deg, #0d9488, #0f766e); box-shadow: 0 4px 10px rgba(13, 148, 136, 0.3);">
                        <span>👥</span> Staff Directory (.csv)
                    </a>
                </div>
            </div>

            <button type="submit" name="update_settings" style="margin-top:25px; width:100%;">Save Settings</button>
        </form>

        <div class="back-link" style="margin-top:20px; text-align:center;">
            <a href="dashboard.php" style="color:var(--primary); text-decoration:none; font-weight:500;">← Back to Dashboard</a>
        </div>
    </div>

    <!-- DBeaver Guide Section -->
    <div class="settings-box" style="max-width:800px; margin: 40px auto; background:var(--surface); padding:30px; border-radius:var(--radius-lg); box-shadow:var(--shadow);">
        <h3 style="font-size: 20px; font-weight: 800; margin-bottom: 20px; color: var(--text); display: flex; align-items: center; gap: 10px;">📖 Database Restore Guide (DBeaver)</h3>
        <p style="margin-bottom:30px; color:var(--text-muted); font-size:14px;">Follow these steps to safely restore a <code>.sql</code> backup to your Aiven cloud database using DBeaver. (Do not upload large SQL files via PHP).</p>
        
        <div style="list-style: none; padding: 0;">
            <div style="display: flex; gap: 20px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border);">
                <div style="background: var(--primary); color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">1</div>
                <div>
                    <h4 style="font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 6px;">Download DBeaver</h4>
                    <p style="color: var(--text-muted); font-size: 14px; margin: 0;">Install the free <a href="https://dbeaver.io/" target="_blank" style="color: var(--primary); font-weight: 600;">DBeaver Community Edition</a> on your computer.</p>
                </div>
            </div>
            <div style="display: flex; gap: 20px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border);">
                <div style="background: var(--primary); color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">2</div>
                <div>
                    <h4 style="font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 6px;">Connect to MySQL</h4>
                    <p style="color: var(--text-muted); font-size: 14px; margin: 0;">Create a new MySQL connection using your cloud database host, port, username, password, and database name.</p>
                </div>
            </div>
            <div style="display: flex; gap: 20px;">
                <div style="background: var(--primary); color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">3</div>
                <div>
                    <h4 style="font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 6px;">Execute SQL Script</h4>
                    <p style="color: var(--text-muted); font-size: 14px; margin: 0;">Open your downloaded <code>.sql</code> backup file in DBeaver and press <kbd style="background:var(--bg); padding:2px 6px; border-radius:4px; border:1px solid var(--border);">Ctrl+Alt+X</kbd> to execute script and restore.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateFreqNotice(freq) {
    const box = document.getElementById('freqPromptNotice');
    const txt = document.getElementById('freqNoticeText');
    if (freq === 'never') {
        box.style.display = 'none';
    } else {
        box.style.display = 'block';
        const labels = {
            '24_hours': 'Every 24 Hours (Daily)',
            '7_days': 'Every 7 Days (Weekly)',
            '14_days': 'Every 14 Days (Bi-Weekly)',
            '30_days': 'Every 30 Days (Monthly)',
            '90_days': 'Every 3 Months (Quarterly)',
            '180_days': 'Every 6 Months',
            '365_days': 'Every 1 Year (Annually)'
        };
        const sched = labels[freq] || freq;
        txt.innerHTML = '🔔 <strong>Scheduled Active:</strong> The system will automatically compress and email backups <strong>' + sched + '</strong> to <code><?php echo htmlspecialchars($admin['email'] ?? 'admin email'); ?></code>. Click <strong>"Save Settings"</strong> to apply.';
    }
}

function refreshExportUrls() {
    const allTime = document.getElementById('attAllTimeCheck').checked;
    const monthVal = allTime ? 'all' : (document.getElementById('attMonthSelect').value || 'all');
    
    document.getElementById('attMonthSelect').disabled = allTime;
    document.getElementById('attMonthSelect').style.opacity = allTime ? '0.5' : '1';

    document.getElementById('btnAttCsv').href = 'backup.php?action=attendance_excel&month=' + encodeURIComponent(monthVal);
    document.getElementById('btnAttZip').href = 'backup.php?action=attendance_excel&month=' + encodeURIComponent(monthVal) + '&zip=1';
    document.getElementById('btnAttEmail').href = 'backup.php?action=email_attendance&month=' + encodeURIComponent(monthVal);
}
</script>
</body>
</html>
