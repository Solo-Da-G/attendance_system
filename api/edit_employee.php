<?php
include(__DIR__ . "/includes/config.php");

// Error reporting (suppress deprecation warnings on PHP 8.5+)
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);

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

// Check if user is Super Admin
$is_super_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';

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

    $old_staff_id = $employee['staff_id'];
    $new_staff_id = $old_staff_id;
    if ($is_super_admin && isset($_POST['staff_id'])) {
        $new_staff_id = trim($_POST['staff_id']);
    }

    // Keep form values in $employee in case of error
    $employee['staff_id']       = $new_staff_id;
    $employee['full_name']      = $full_name;
    $employee['job_title']      = $job_title;
    $employee['department']     = $department;
    $employee['branch']         = $branch;
    $employee['email']          = $email;
    $employee['phone']          = $phone;
    $employee['fingerprint_id'] = $finger_id;

    // Validate staff_id
    if ($new_staff_id === '') {
        $error_msg = "Staff ID cannot be empty.";
    } elseif ($new_staff_id !== $old_staff_id) {
        $dup_staff = $conn->prepare("SELECT id, full_name FROM staff WHERE staff_id = ? AND id != ? LIMIT 1");
        if ($dup_staff) {
            $dup_staff->bind_param("si", $new_staff_id, $id);
            $dup_staff->execute();
            $dup_staff_res = $dup_staff->get_result();
            if ($dup_staff_row = $dup_staff_res->fetch_assoc()) {
                $error_msg = "Staff ID '{$new_staff_id}' is already assigned to {$dup_staff_row['full_name']}. User IDs must be unique.";
            }
            $dup_staff->close();
        }
    }

    // Check for duplicate fingerprint_id across other active employees
    if (!isset($error_msg) && $finger_id !== '') {
        $dup_check = $conn->prepare("SELECT id, full_name, staff_id FROM staff WHERE fingerprint_id = ? AND id != ? AND deleted_at IS NULL LIMIT 1");
        if ($dup_check) {
            $dup_check->bind_param("si", $finger_id, $id);
            $dup_check->execute();
            $dup_res = $dup_check->get_result();
            if ($dup_row = $dup_res->fetch_assoc()) {
                $error_msg = "Fingerprint User ID '{$finger_id}' is already assigned to {$dup_row['full_name']} (Staff ID: {$dup_row['staff_id']}). Each employee must have a unique ZKTeco Device User ID.";
            }
            $dup_check->close();
        }
    }

    if (!isset($error_msg)) {
        // Start building the update query
        $updates = [];
        $params = [];
        $types = "";

        if ($new_staff_id !== $old_staff_id) {
            $updates[] = "staff_id = ?";
            $params[]  = $new_staff_id;
            $types    .= "s";
        }

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
            $default_pass = $new_staff_id;
            $hashed       = password_hash($default_pass, PASSWORD_DEFAULT);
            $updates[]    = "password = ?";
            $params[]     = $hashed;
            $types       .= "s";
            $updates[]    = "must_change_password = 1";
        } elseif (!empty($_POST['new_password'])) {
            $hashed    = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $updates[] = "password = ?";
            $params[]  = $hashed;
            $types    .= "s";
        }

        // Handle photo upload
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $imgData = file_get_contents($_FILES['photo']['tmp_name']);
            $imgType = "";
            if (function_exists('mime_content_type')) {
                $imgType = @mime_content_type($_FILES['photo']['tmp_name']);
            }
            if (empty($imgType) && function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $imgType = finfo_file($finfo, $_FILES['photo']['tmp_name']);
                    if (PHP_VERSION_ID < 80500 && is_resource($finfo)) {
                        @finfo_close($finfo);
                    }
                }
            }
            if (empty($imgType)) {
                $imgType = $_FILES['photo']['type'] ?? 'image/jpeg';
            }

            if (strpos($imgType, 'image/') === 0) {
                // Optimize photo size to keep database lightweight & fast (max 450x450 JPEG)
                if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
                    $src = @imagecreatefromstring($imgData);
                    if ($src !== false) {
                        $w = imagesx($src);
                        $h = imagesy($src);
                        $maxDim = 450;
                        if ($w > $maxDim || $h > $maxDim || strlen($imgData) > 150000) {
                            $ratio = min($maxDim / $w, $maxDim / $h);
                            $nw = max(1, (int)round($w * $ratio));
                            $nh = max(1, (int)round($h * $ratio));
                            $dst = imagecreatetruecolor($nw, $nh);
                            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                            ob_start();
                            imagejpeg($dst, null, 82);
                            $optimized = ob_get_clean();
                            imagedestroy($dst);
                            imagedestroy($src);
                            if (!empty($optimized)) {
                                $imgData = $optimized;
                                $imgType = 'image/jpeg';
                            }
                        } else {
                            imagedestroy($src);
                        }
                    }
                }

                $photo     = 'data:' . $imgType . ';base64,' . base64_encode($imgData);
                $updates[] = "photo = ?";
                $params[]  = $photo;
                $types    .= "s";
                $employee['photo'] = $photo;
            }
        }

        // Build final SQL with WHERE clause
        $sql = "UPDATE staff SET " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $id;
        $types   .= "i";

        // Execute inside transaction for data integrity across all linked tables
        try {
            $conn->begin_transaction();

            // If staff_id changed, cascade update across all tables
            if ($new_staff_id !== $old_staff_id) {
                // 1. Attendance records (history, photos, sessions)
                $att_stmt = $conn->prepare("UPDATE attendance SET staff_id = ? WHERE staff_id = ?");
                if ($att_stmt) {
                    $att_stmt->bind_param("ss", $new_staff_id, $old_staff_id);
                    $att_stmt->execute();
                    $att_stmt->close();
                }

                // 2. Field work comments
                $fw_stmt = $conn->prepare("UPDATE field_work_comments SET staff_id = ? WHERE staff_id = ?");
                if ($fw_stmt) {
                    $fw_stmt->bind_param("ss", $new_staff_id, $old_staff_id);
                    $fw_stmt->execute();
                    $fw_stmt->close();
                }

                // 3. Biometrics credentials
                $bio_stmt = $conn->prepare("UPDATE staff_biometrics SET staff_id = ? WHERE staff_id = ?");
                if ($bio_stmt) {
                    $bio_stmt->bind_param("ss", $new_staff_id, $old_staff_id);
                    $bio_stmt->execute();
                    $bio_stmt->close();
                }

                // 4. Audit logs
                $aud_stmt = $conn->prepare("UPDATE audit_log SET staff_id = ? WHERE staff_id = ?");
                if ($aud_stmt) {
                    $aud_stmt->bind_param("ss", $new_staff_id, $old_staff_id);
                    $aud_stmt->execute();
                    $aud_stmt->close();
                }

                // 5. Notification reads
                $old_uk = 's_' . $old_staff_id;
                $new_uk = 's_' . $new_staff_id;
                $notif_stmt = $conn->prepare("UPDATE notif_reads SET user_key = ? WHERE user_key = ?");
                if ($notif_stmt) {
                    $notif_stmt->bind_param("ss", $new_uk, $old_uk);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }

                // Record audit event for Staff ID modification
                $admin_name = $_SESSION['admin'] ?? 'Super Admin';
                $ip_addr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $audit_event = "Super Admin '{$admin_name}' changed User ID from '{$old_staff_id}' to '{$new_staff_id}' for {$full_name}. All attendance, photos, comments, and audit logs cascaded.";
                $log_stmt = $conn->prepare("INSERT INTO audit_log (staff_id, full_name, event_type, event_detail, ip_address) VALUES (?, ?, 'staff_id_change', ?, ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("ssss", $new_staff_id, $full_name, $audit_event, $ip_addr);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                // If currently authenticated as this staff member in session, update session
                if (isset($_SESSION['staff_id']) && $_SESSION['staff_id'] === $old_staff_id) {
                    $_SESSION['staff_id'] = $new_staff_id;
                }
            }

            // Execute the staff table update
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Database prepare failed: " . $conn->error);
            }
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();

            // Commit transaction
            $conn->commit();

            $msg = "✅ Employee updated successfully!";
            if ($new_staff_id !== $old_staff_id) {
                $msg .= " User ID safely updated to: {$new_staff_id}. All attendance records, photos, comments, and logs remain 100% linked.";
            }
            if (isset($_POST['reset_to_default']) && $_POST['reset_to_default'] == '1') {
                $msg .= " Password reset to: " . $new_staff_id;
            } elseif (!empty($_POST['new_password'])) {
                $msg .= " New password set.";
            }
            echo "<script>alert(" . json_encode($msg) . "); window.location='employees.php';</script>";
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $error_msg = "Database error: " . $e->getMessage();
        }
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

        <?php if ($is_super_admin): ?>
            <label style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
                <span>User ID / Staff ID *</span>
                <span style="background:var(--primary-subtle, #eff6ff); color:var(--primary, #2563eb); font-size:11px; padding:3px 10px; border-radius:999px; font-weight:600; border:1px solid rgba(37,99,235,0.2);">🛡️ Super Admin Editable</span>
            </label>
            <input type="text" id="staffIdInput" name="staff_id" value="<?php echo htmlspecialchars($employee['staff_id'] ?? ''); ?>" required autocomplete="off" style="font-weight:600; letter-spacing:0.5px;">
            <small style="color:var(--text-muted); display:block; margin-top:4px;">As Super Admin, you can edit this User ID. All historical attendance, selfie photos, biometrics, comments, and audit logs will automatically be preserved and linked to the new ID.</small>
        <?php else: ?>
            <label>User ID / Staff ID (Locked)</label>
            <input type="text" value="<?php echo htmlspecialchars($employee['staff_id'] ?? ''); ?>" disabled style="background:#f1f5f9; cursor:not-allowed;">
            <small style="color:var(--text-muted); display:block; margin-top:4px;">Only a Super Admin can modify an employee's User ID.</small>
        <?php endif; ?>

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
        <div class="password-input-wrap">
            <input type="password" id="empNewPassField" name="new_password" placeholder="Enter new password" autocomplete="new-password">
            <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('empNewPassField', this)" aria-label="Show password" title="Show password">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
        <small style="color:var(--text-muted); display:block; margin-top:4px;">Leave blank to keep current password</small>
        
        <div style="margin: 15px 0; padding: 12px; background: #fef3c7; border-radius: 12px;">
            <label style="cursor: pointer;">
                <input type="checkbox" name="reset_to_default" id="resetToDefaultCheck" value="1">
                🔄 <strong>Reset password to Staff ID: <code id="staffIdDisplay"><?php echo htmlspecialchars($employee['staff_id'] ?? ''); ?></code></strong>
            </label>
            <br><small style="color:var(--text-muted);">Check this box and click Save to reset their password to their Staff ID. They will be required to change it on their next login.</small>
        </div>

        <hr style="margin: 25px 0;">
        
        <h4>📷 Profile Photo</h4>
        
        <label>Current Photo</label><br>
        <?php if (!empty($employee['photo']) && strlen($employee['photo']) > 100): ?>
            <img src="photo.php?type=staff&id=<?php echo urlencode($employee['staff_id']); ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:10px 0;border:2px solid var(--primary);" alt="Current photo" onerror="this.onerror=null;this.style.display='none';">
            <br>
        <?php else: ?>
            <p style="color:var(--text-muted);">No photo uploaded yet</p>
        <?php endif; ?>

        <label>Change Photo (optional)</label>
        <div style="display:flex; align-items:center; gap:14px; margin-bottom:12px;">
            <div id="editPhotoPreviewWrap" style="width:64px; height:64px; border-radius:16px; border:2px dashed #cbd5e1; display:flex; align-items:center; justify-content:center; background:#f8fafc; overflow:hidden; flex-shrink:0;">
                <span id="editPhotoPlaceholder" style="font-size:24px;">📷</span>
                <img id="editPhotoPreview" src="" alt="New Photo Preview" style="display:none; width:100%; height:100%; object-fit:cover;">
            </div>
            <div style="flex:1;">
                <input type="file" id="editEmpPhotoInput" name="photo" accept="image/jpeg,image/png,image/webp,image/jpg" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #e2e8f0; border-radius:12px; font-size:13px;" onchange="previewAndCompressPhoto(this, 'editPhotoPreview', 'editPhotoPlaceholder', 'editPhotoSizeHint')">
                <small id="editPhotoSizeHint" style="color:var(--text-muted); display:block; margin-top:4px;">Upload a clear front-facing photo (auto-optimized for fast face recognition)</small>
            </div>
        </div>

        <div style="display:flex;gap:12px;margin-top:30px;">
            <button type="submit" name="update_employee" style="background:var(--primary);">💾 Save Changes</button>
            <a href="employees.php"><button type="button" style="background:#f1f5f9; color:#475569;">Cancel</button></a>
        </div>
    </form>

    <div class="footer" style="margin-top: 40px;">
        &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Mbewu
    </div>
<script>
function togglePassVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    const svg = btn ? btn.querySelector('svg') : null;
    if (svg) {
        if (isPass) {
            svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
            btn.style.color = '#2563eb';
            btn.setAttribute('aria-label', 'Hide password');
            btn.setAttribute('title', 'Hide password');
        } else {
            svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
            btn.style.color = '#94a3b8';
            btn.setAttribute('aria-label', 'Show password');
            btn.setAttribute('title', 'Show password');
        }
    }
}

// Live update password reset hint when Super Admin changes User ID
const staffIdInput = document.getElementById('staffIdInput');
const staffIdDisplay = document.getElementById('staffIdDisplay');
if (staffIdInput && staffIdDisplay) {
    staffIdInput.addEventListener('input', function() {
        staffIdDisplay.textContent = this.value.trim() || '—';
    });
}

// Client-side canvas image downsampler
function previewAndCompressPhoto(input, previewImgId, placeholderId, hintId) {
    const file = input.files && input.files[0];
    const previewImg = document.getElementById(previewImgId);
    const placeholder = document.getElementById(placeholderId);
    const hint = document.getElementById(hintId);

    if (!file) {
        if (previewImg) previewImg.style.display = 'none';
        if (placeholder) placeholder.style.display = 'block';
        if (hint) hint.textContent = 'Upload front-facing photo (auto-optimized)';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            const maxDim = 450;
            let w = img.width;
            let h = img.height;
            if (w > maxDim || h > maxDim) {
                if (w > h) {
                    h = Math.round((h * maxDim) / w);
                    w = maxDim;
                } else {
                    w = Math.round((w * maxDim) / h);
                    h = maxDim;
                }
            }

            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, w, h);

            canvas.toBlob(function(blob) {
                if (blob) {
                    const origKb = Math.round(file.size / 1024);
                    const optKb = Math.round(blob.size / 1024);
                    try {
                        const newFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        const dt = new DataTransfer();
                        dt.items.add(newFile);
                        input.files = dt.files;
                    } catch (err) {
                        // Older browsers fallback
                    }

                    if (previewImg) {
                        previewImg.src = canvas.toDataURL('image/jpeg', 0.82);
                        previewImg.style.display = 'block';
                    }
                    if (placeholder) placeholder.style.display = 'none';
                    if (hint) {
                        hint.innerHTML = `<span style="color:#059669; font-weight:600;">✅ Optimized: ${optKb} KB</span> <span style="color:#94a3b8;">(was ${origKb} KB)</span>`;
                    }
                }
            }, 'image/jpeg', 0.82);
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}
</script>
</body>
</html>
