<?php
include(__DIR__ . "/includes/config.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Determine Employee ID from GET or POST
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) {
    header("Location: employees.php");
    exit;
}

// Auto-heal schema: Ensure fingerprint_id and photo columns are sufficiently sized
try {
    $col_res = $conn->query("SHOW COLUMNS FROM `staff` LIKE 'fingerprint_id'");
    if ($col_res && $row = $col_res->fetch_assoc()) {
        $col_type = strtolower($row['Type'] ?? '');
        if (!preg_match('/varchar\((?:25[5-9]|2[6-9]\d|[3-9]\d\d|\d{4,})\)|text/i', $col_type)) {
            @$conn->query("ALTER TABLE `staff` MODIFY COLUMN `fingerprint_id` VARCHAR(255) NULL DEFAULT NULL");
        }
    } else {
        @$conn->query("ALTER TABLE `staff` ADD COLUMN `fingerprint_id` VARCHAR(255) NULL DEFAULT NULL AFTER `branch`");
    }

    $photo_res = $conn->query("SHOW COLUMNS FROM `staff` LIKE 'photo'");
    if ($photo_res && $prow = $photo_res->fetch_assoc()) {
        $ptype = strtolower($prow['Type'] ?? '');
        if (strpos($ptype, 'text') === false) {
            @$conn->query("ALTER TABLE `staff` MODIFY COLUMN `photo` LONGTEXT NULL");
        }
    }
} catch (Throwable $e) {
    // Non-fatal if restricted permissions
}

// Fetch employee data
$stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee not found.");
}

// Handle update form submission
if (isset($_POST['update_employee'])) {
    $id         = (int)$_POST['id'];
    $full_name  = trim($_POST['full_name'] ?? '');
    $job_title  = trim($_POST['job_title'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $branch     = trim($_POST['branch'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $finger_id  = trim($_POST['fingerprint_id'] ?? '');

    // Keep form values in $employee in case of error
    $employee['full_name']      = $full_name;
    $employee['job_title']      = $job_title;
    $employee['department']     = $department;
    $employee['branch']         = $branch;
    $employee['email']          = $email;
    $employee['phone']          = $phone;
    $employee['fingerprint_id'] = $finger_id;

    // Start building the update query
    $updates = [];
    $params = [];
    $types = "";

    // Basic fields
    $updates[] = "full_name = ?";
    $params[]  = $full_name;
    $types    .= "s";

    $updates[] = "job_title = ?";
    $params[]  = $job_title;
    $types    .= "s";

    $updates[] = "department = ?";
    $params[]  = $department;
    $types    .= "s";

    $updates[] = "branch = ?";
    $params[]  = $branch;
    $types    .= "s";

    $updates[] = "email = ?";
    $params[]  = $email;
    $types    .= "s";

    $updates[] = "phone = ?";
    $params[]  = $phone;
    $types    .= "s";

    // Handle fingerprint_id: if empty set to NULL, otherwise safely truncate to 255 chars
    if ($finger_id === '') {
        $updates[] = "fingerprint_id = NULL";
    } else {
        $updates[] = "fingerprint_id = ?";
        $params[]  = substr($finger_id, 0, 255);
        $types    .= "s";
    }

    // Handle password update (reset to default takes precedence over manual new password)
    if (isset($_POST['reset_to_default']) && $_POST['reset_to_default'] == '1') {
        $default_pass = $employee['staff_id'];
        $hashed       = password_hash($default_pass, PASSWORD_DEFAULT);
        $updates[]    = "password = ?";
        $params[]     = $hashed;
        $types       .= "s";
    } elseif (!empty($_POST['new_password'])) {
        $hashed    = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $updates[] = "password = ?";
        $params[]  = $hashed;
        $types    .= "s";
    }

    // Handle photo upload
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $imgData = file_get_contents($_FILES['photo']['tmp_name']);
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $imgType = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);

        if (strpos($imgType, 'image/') === 0) {
            $photo     = 'data:' . $imgType . ';base64,' . base64_encode($imgData);
            $updates[] = "photo = ?";
            $params[]  = $photo;
            $types    .= "s";
            $employee['photo'] = $photo;
        }
    }

    // Add WHERE condition
    $updates[] = "id = ?";
    $params[]  = $id;
    $types    .= "i";

    // Build final SQL
    $sql = "UPDATE staff SET " . implode(", ", $updates);

    // Prepare and execute with error handling
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $error_msg = "Database prepare failed: " . $conn->error;
        } else {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            if ($stmt->execute()) {
                $msg = "✅ Employee updated successfully!";
                if (isset($_POST['reset_to_default']) && $_POST['reset_to_default'] == '1') {
                    $msg .= " Password reset to: " . $employee['staff_id'];
                } elseif (!empty($_POST['new_password'])) {
                    $msg .= " New password set.";
                }
                echo "<script>alert(" . json_encode($msg) . "); window.location='employees.php';</script>";
                exit;
            } else {
                $error_msg = "Execute failed: " . $stmt->error;
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        $error_msg = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Employee — Attendance System</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
    <h2>✏️ Edit Employee</h2>
    
    <?php if (isset($error_msg)): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:12px; margin-bottom:20px; border:1px solid #f87171;">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data" style="max-width:600px;">
        <input type="hidden" name="id" value="<?php echo (int)($employee['id'] ?? 0); ?>">

        <label>Staff ID (Cannot be changed)</label>
        <input type="text" value="<?php echo htmlspecialchars($employee['staff_id'] ?? ''); ?>" disabled style="background:#f1f5f9; cursor:not-allowed;">

        <label>Full Name *</label>
        <input type="text" name="full_name" value="<?php echo htmlspecialchars($employee['full_name'] ?? ''); ?>" required>

        <label>Job Title</label>
        <input type="text" name="job_title" value="<?php echo htmlspecialchars($employee['job_title'] ?? ''); ?>">

        <label>Department</label>
        <input type="text" name="department" value="<?php echo htmlspecialchars($employee['department'] ?? ''); ?>">

        <label>Branch</label>
        <?php
        $branch_options = [];
        $br_res = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name ASC");
        if ($br_res) {
            while ($br = $br_res->fetch_assoc()) {
                $branch_options[] = $br['branch_name'];
            }
        }
        $cur_branch = $employee['branch'] ?? '';
        if (!empty($branch_options)):
        ?>
        <select name="branch" required>
            <option value="">Select Branch</option>
            <?php foreach ($branch_options as $bn): ?>
            <option value="<?php echo htmlspecialchars($bn); ?>" <?php echo ($cur_branch === $bn) ? 'selected' : ''; ?>><?php echo htmlspecialchars($bn); ?></option>
            <?php endforeach; ?>
        </select>
        <?php else: ?>
        <input type="text" name="branch" value="<?php echo htmlspecialchars($cur_branch); ?>" required>
        <?php endif; ?>

        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">

        <label>Phone</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">

        <label>Fingerprint User ID (ZKTeco Device)</label>
        <input type="text" name="fingerprint_id" value="<?php echo htmlspecialchars($employee['fingerprint_id'] ?? ''); ?>" placeholder="e.g. 1" maxlength="255" autocomplete="off">
        <small style="color:var(--text-muted); display:block; margin-top:4px;">Used for ZKTeco biometric device matching (numeric ID or device user string, max 255 characters)</small>

        <hr style="margin: 25px 0;">
        
        <h4>🔐 Password Management</h4>
        
        <label>Set New Password (Optional)</label>
        <input type="password" name="new_password" placeholder="Enter new password" autocomplete="new-password">
        <small style="color:var(--text-muted); display:block; margin-top:4px;">Leave blank to keep current password</small>
        
        <div style="margin: 15px 0; padding: 12px; background: #fef3c7; border-radius: 12px;">
            <label style="cursor: pointer;">
                <input type="checkbox" name="reset_to_default" value="1">
                🔄 <strong>Reset password to Staff ID: <code><?php echo htmlspecialchars($employee['staff_id'] ?? ''); ?></code></strong>
            </label>
            <br><small style="color:var(--text-muted);">Check this box and click Save to reset their password to their Staff ID</small>
        </div>

        <hr style="margin: 25px 0;">
        
        <h4>📷 Profile Photo</h4>
        
        <label>Current Photo</label><br>
        <?php if (!empty($employee['photo']) && strlen($employee['photo']) > 100): ?>
            <img src="<?php echo htmlspecialchars($employee['photo']); ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:10px 0;border:2px solid var(--primary);" alt="Current photo">
            <br>
        <?php else: ?>
            <p style="color:var(--text-muted);">No photo uploaded yet</p>
        <?php endif; ?>

        <label>Change Photo (optional)</label>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/jpg">
        <small style="color:var(--text-muted); display:block; margin-top:4px;">Upload a clear face photo for face recognition (JPG or PNG)</small>

        <div style="display:flex;gap:12px;margin-top:30px;">
            <button type="submit" name="update_employee" style="background:var(--primary);">💾 Save Changes</button>
            <a href="employees.php"><button type="button" style="background:#f1f5f9; color:#475569;">Cancel</button></a>
        </div>
    </form>

    <div class="footer" style="margin-top: 40px;">
        &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Mbewu
    </div>
</div>

</body>
</html>
