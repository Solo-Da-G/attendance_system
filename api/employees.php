<?php
include(__DIR__ . "/includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle form submission (Add Employee)
if (isset($_POST['add_employee'])) {
    $staff_id    = trim($_POST['staff_id']);
    $full_name   = trim($_POST['full_name']);
    $job_title   = trim($_POST['job_title']);
    $department  = trim($_POST['department']);
    $branch      = trim($_POST['branch']);
    $email       = trim($_POST['email']);
    $phone       = trim($_POST['phone']);

    $plain_password = !empty($_POST['password']) ? $_POST['password'] : $staff_id;
    $password       = password_hash($plain_password, PASSWORD_DEFAULT);

    $photo = "";
    if (!empty($_POST['photo_base64']) && strpos($_POST['photo_base64'], 'data:image') === 0) {
        $photo = trim($_POST['photo_base64']);
    } elseif (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
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

        $photo = 'data:' . $imgType . ';base64,' . base64_encode($imgData);
    }

    $stmt = false;
    $has_mcp = function_exists('db_has_column') && db_has_column($conn, 'staff', 'must_change_password');
    if ($has_mcp) {
        $stmt = $conn->prepare("INSERT INTO staff (staff_id, full_name, job_title, department, branch, email, phone, password, photo, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    }
    if (!$stmt) {
        $stmt = $conn->prepare("INSERT INTO staff (staff_id, full_name, job_title, department, branch, email, phone, password, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    }

    if ($stmt) {
        $stmt->bind_param("sssssssss", $staff_id, $full_name, $job_title, $department, $branch, $email, $phone, $password, $photo);
        try {
            if ($stmt->execute()) {
                echo "<script>alert('Employee added successfully! Default password is: " . addslashes($plain_password) . "'); window.location='employees.php';</script>";
                exit;
            } else {
                echo "<script>alert('Error: " . addslashes($stmt->error) . "');</script>";
            }
        } catch (Throwable $e) {
            $errText = (strpos($e->getMessage(), 'Duplicate entry') !== false)
                ? "A staff member with Staff ID '$staff_id' already exists."
                : "Database error: " . $e->getMessage();
            echo "<script>alert(" . json_encode($errText) . ");</script>";
        }
        $stmt->close();
    }
}

// Get branch options
$branch_options = [];
$br_res = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name ASC");
if ($br_res) {
    while ($br = $br_res->fetch_assoc()) {
        $branch_options[] = $br['branch_name'];
    }
}

// Get all employees (project lightweight columns; photo streamed on-demand via photo.php)
$result = $conn->query("
    SELECT 
        id, staff_id, full_name, job_title, department, branch, email, phone,
        (CASE WHEN photo IS NOT NULL AND LENGTH(photo) > 100 THEN 1 ELSE 0 END) AS has_photo,
        COALESCE(CRC32(photo), 0) AS photo_v
    FROM staff 
    WHERE deleted_at IS NULL 
    ORDER BY id DESC
");
$total_employees = $result ? $result->num_rows : 0;
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
<title>Employees</title>
<link rel="stylesheet" href="/asset/css/style.css">
<style>
    .table-wrapper {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        background: white;
        border-radius: 24px;
        border: 1px solid var(--border);
        margin-top: 20px;
    }
    
    .employees-table {
        width: 100%;
        min-width: 1000px;
        border-collapse: collapse;
    }

    @media (max-width: 768px) {
        .table-wrapper {
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            overflow: visible !important;
            margin-top: 14px !important;
        }
        .employees-table {
            min-width: 0 !important;
            width: 100% !important;
        }
        .add-btn, .export-btn {
            width: 100% !important;
            min-height: 44px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
    }
    
    .employees-table th,
    .employees-table td {
        padding: 14px 16px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .employees-table th {
        background: #f8fafc;
        font-weight: 700;
        font-size: 13px;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .employees-table tr:hover {
        background: #f1f5f9;
    }
    
    .staff-photo {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e2e8f0;
    }
    
    .no-photo {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: #64748b;
    }
    
    .action-btn {
        padding: 6px 12px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        margin: 0 4px;
    }
    
    .edit-btn {
        background: #3b82f6;
        color: white;
    }
    
    .delete-btn {
        background: #ef4444;
        color: white;
    }
    
    @media (max-width: 768px) {
        .mobile-swipe-hint {
            display: flex !important;
        }
        .employees-table th,
        .employees-table td {
            padding: 10px 12px;
            font-size: 13px;
        }
        .staff-photo, .no-photo {
            width: 36px;
            height: 36px;
            font-size: 14px;
        }
    }

    .pagination-btn {
        padding: 7px 12px;
        border: 1px solid #cbd5e1;
        background: white;
        color: #1e293b;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        min-height: 34px;
    }
    .pagination-btn:hover:not(:disabled) {
        background: #f1f5f9;
        border-color: #94a3b8;
    }
    .pagination-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 2px 6px rgba(37,99,235,0.3);
    }
    .pagination-btn:disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }
    .filter-select:focus, .search-input:focus {
        border-color: var(--primary) !important;
        outline: none;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
    }
</style>
</head>
<body class="app-page employees-page">

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
        <div>
            <h2 style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span>📋 Employees</span>
                <span style="font-size:13px; background:#e0e7ff; color:#3730a3; padding:4px 12px; border-radius:20px; font-weight:700;">
                    <?php echo (int)$total_employees; ?> Active Staff
                </span>
            </h2>
            <p class="subtitle">Manage staff records and profile photos for face recognition (Supports 500+ staff)</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="backup.php?action=employees_excel" target="_blank" class="export-btn" style="padding:12px 20px; background:linear-gradient(135deg, #10b981, #059669); color:white; border:none; border-radius:12px; text-decoration:none; font-weight:700; font-size:14px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 10px rgba(16,185,129,0.3);">
                <span>📊</span> Export to Excel
            </a>
            <button type="button" id="openAddEmployeeBtn" class="add-btn" onclick="openModal()" style="padding:12px 24px; background:var(--primary); color:white; border:none; border-radius:12px; cursor:pointer; font-weight:700; font-size:14px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 10px rgba(37,99,235,0.25);">+ Add New Employee</button>
        </div>
    </div>

    <!-- Flexible Search, Branch Filter & Page Size Selector -->
    <div class="search-filter-container" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:14px;">
        <div class="search-section" style="flex:2; min-width:240px; margin-bottom:0;">
            <span class="search-icon">🔍</span>
            <input type="text" id="employeeSearch" class="search-input" placeholder="Search by name, staff ID, branch, job title, email..." oninput="handleSearchFilter()">
        </div>
        <div style="flex:1; min-width:180px;">
            <select id="branchFilter" class="filter-select" onchange="handleSearchFilter()" style="width:100%; height:48px; padding:10px 14px; border-radius:14px; border:1px solid #cbd5e1; background:white; font-size:14px; font-weight:600; color:#334155; cursor:pointer;">
                <option value="">🏢 All Branches</option>
                <?php foreach ($branch_options as $bn): ?>
                <option value="<?php echo htmlspecialchars($bn); ?>"><?php echo htmlspecialchars($bn); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <label for="pageSizeSelect" style="font-size:13px; font-weight:600; color:#64748b; white-space:nowrap;">Show:</label>
            <select id="pageSizeSelect" onchange="changePageSize(this.value)" style="height:48px; padding:10px 14px; border-radius:14px; border:1px solid #cbd5e1; background:white; font-size:14px; font-weight:700; color:#1e293b; cursor:pointer;">
                <option value="25">25</option>
                <option value="50" selected>50</option>
                <option value="100">100</option>
                <option value="all">All</option>
            </select>
        </div>
    </div>

    <!-- Pagination & Record Count Summary Bar -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px; font-size:13px; color:#64748b;">
        <div>
            Showing <strong id="showingCount" style="color:#0f172a;">0</strong> of <strong id="totalCount" style="color:#0f172a;"><?php echo (int)$total_employees; ?></strong> employees
            <span id="filterNotice" style="display:none; color:#2563eb; margin-left:6px; font-weight:600;">(filtered)</span>
        </div>
        <div id="paginationTop" class="pagination-controls" style="display:flex; gap:6px; align-items:center;"></div>
    </div>

    <div class="mobile-swipe-hint" style="background:#f0f9ff; color:#0369a1; border:1px solid #bae6fd; padding:10px 14px; border-radius:12px; font-size:13px; font-weight:600; margin-bottom:12px; display:none; align-items:center; gap:8px;">
        <span>👉</span>
        <span>Swipe table sideways to view all staff info &amp; Edit / Delete actions</span>
    </div>

    <div class="table-wrapper">
        <table class="employees-table responsive-table" id="employeesTable">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Staff ID</th>
                    <th>Full Name</th>
                    <th>Job Title</th>
                    <th>Department</th>
                    <th>Branch</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $hasPhoto = !empty($row['has_photo']);
                        $staffId  = htmlspecialchars($row['staff_id'] ?? '');
                    ?>
                        <tr class="employee-row" data-branch="<?php echo htmlspecialchars(strtolower($row['branch'] ?? '')); ?>">
                            <td data-label="Photo">
                                <?php if ($hasPhoto): ?>
                                    <img src="photo.php?type=staff&id=<?php echo urlencode($row['staff_id']); ?>&v=<?php echo $row['photo_v']; ?>" class="staff-photo" alt="Photo" loading="lazy" onerror="this.onerror=null;this.parentElement.innerHTML='<div class=\'no-photo\'>📷</div>';">
                                <?php else: ?>
                                    <div class="no-photo">📷</div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Staff ID"><code><?php echo $staffId; ?></code></td>
                            <td data-label="Full Name"><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                            <td data-label="Job Title"><?php echo htmlspecialchars($row['job_title'] ?? '—'); ?></td>
                            <td data-label="Department"><?php echo htmlspecialchars($row['department'] ?? '—'); ?></td>
                            <td data-label="Branch"><?php echo htmlspecialchars($row['branch'] ?? '—'); ?></td>
                            <td data-label="Email"><?php echo htmlspecialchars($row['email'] ?? '—'); ?></td>
                            <td data-label="Phone"><?php echo htmlspecialchars($row['phone'] ?? '—'); ?></td>
                            <td data-label="Actions" style="white-space:nowrap;">
                                <a href="edit_employee.php?id=<?php echo $row['id']; ?>">
                                    <button class="action-btn edit-btn" style="min-height:36px;padding:6px 14px;">Edit</button>
                                </a>
                                <a href="delete_employee.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this employee?');">
                                    <button class="action-btn delete-btn" style="min-height:36px;padding:6px 14px;">Delete</button>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                <tr id="noResultsRow" style="<?php echo ($total_employees > 0) ? 'display:none;' : ''; ?>">
                    <td colspan="9" style="text-align:center; padding:40px; color:#64748b;">
                        <?php echo ($total_employees > 0) ? 'No employees match your search.' : 'No employees found. Click "Add New Employee" to get started.'; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Bottom Pagination Controls -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:16px;">
        <div style="font-size:13px; color:#64748b;">
            Showing <span id="showingBottomCount" style="font-weight:700; color:#0f172a;">0</span> of <span id="totalBottomCount" style="font-weight:700; color:#0f172a;"><?php echo (int)$total_employees; ?></span> employees
        </div>
        <div id="paginationBottom" class="pagination-controls" style="display:flex; gap:6px; align-items:center;"></div>
    </div>

    <div class="footer">
        &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Mbewu
    </div>
</div>

<!-- Add Employee Modal -->
<div id="addEmployeeModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:10000; align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div class="modal-content" style="background:white; padding:32px; border-radius:24px; max-width:520px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); position:relative; box-sizing:border-box;">
        <span class="close-btn" onclick="closeModal()" style="position:absolute; top:20px; right:24px; font-size:28px; line-height:1; cursor:pointer; color:#64748b; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:8px;" title="Close">&times;</span>
        <h3 style="margin:0 0 20px 0; font-size:20px; color:#0f172a; display:flex; align-items:center; gap:8px;">
            <span>👤</span> Add New Employee
        </h3>
        <form method="POST" enctype="multipart/form-data">
            <label>Staff ID *</label>
            <input type="text" name="staff_id" placeholder="e.g. EMP001" required style="width:100%; box-sizing:border-box; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Full Name *</label>
            <input type="text" name="full_name" placeholder="John Doe" required style="width:100%; box-sizing:border-box; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Job Title</label>
            <input type="text" name="job_title" placeholder="Software Engineer" style="width:100%; box-sizing:border-box; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Department</label>
            <input type="text" name="department" placeholder="IT Department" style="width:100%; box-sizing:border-box; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Branch</label>
            <?php if (!empty($branch_options)): ?>
            <select name="branch" style="width:100%; box-sizing:border-box; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">
                <option value="">Select branch</option>
                <?php foreach ($branch_options as $bn): ?>
                <option value="<?php echo htmlspecialchars($bn); ?>"><?php echo htmlspecialchars($bn); ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="text" name="branch" placeholder="Add branches first" style="width:100%; box-sizing:border-box; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">
            <?php endif; ?>

            <label>Email</label>
            <input type="email" name="email" placeholder="john@example.com" style="width:100%; box-sizing:border-box; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Mobile No</label>
            <input type="text" name="phone" placeholder="+234..." style="width:100%; box-sizing:border-box; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Password (Optional)</label>
            <div class="password-input-wrap" style="margin-bottom:6px;">
                <input type="password" id="addEmpPassField" name="password" placeholder="Leave blank to use Staff ID" style="width:100%; box-sizing:border-box; padding:12px; border:1px solid #e2e8f0; border-radius:12px;">
                <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('addEmpPassField', this)" aria-label="Show password" title="Show password">
                    <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <small style="color:var(--text-muted); display:block; margin-bottom:16px;">Default: Staff ID</small>

            <label>Profile Photo (for Face Recognition)</label>
            <div style="display:flex; align-items:center; gap:14px; margin-bottom:14px;">
                <div id="addPhotoPreviewWrap" style="width:64px; height:64px; border-radius:16px; border:2px dashed #cbd5e1; display:flex; align-items:center; justify-content:center; background:#f8fafc; overflow:hidden; flex-shrink:0;">
                    <span id="addPhotoPlaceholder" style="font-size:24px;">👤</span>
                    <img id="addPhotoPreview" src="" alt="Preview" style="display:none; width:100%; height:100%; object-fit:cover;">
                </div>
                <div style="flex:1;">
                    <input type="hidden" name="photo_base64" id="addPhotoBase64" value="">
                    <input type="file" id="addEmpPhotoInput" name="photo" accept="image/jpeg,image/png,image/webp,image/jpg" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #e2e8f0; border-radius:12px; font-size:13px;" onchange="previewAndCompressPhoto(this, 'addPhotoPreview', 'addPhotoPlaceholder', 'addPhotoSizeHint', 'addPhotoBase64')">
                    <small id="addPhotoSizeHint" style="color:var(--text-muted); display:block; margin-top:4px;">Upload front-facing photo (auto-optimized for fast face recognition)</small>
                </div>
            </div>

            <button type="submit" name="add_employee" style="width:100%; padding:14px; background:var(--primary); color:white; border:none; border-radius:12px; font-weight:700; cursor:pointer;">Add Employee</button>
        </form>
    </div>
</div>

<script>
    function openModal() {
        const modal = document.getElementById('addEmployeeModal');
        if (modal) {
            modal.style.display = 'flex';
            const firstInput = modal.querySelector('input[name="staff_id"]');
            if (firstInput) {
                setTimeout(function() { firstInput.focus(); }, 60);
            }
        }
    }

    function closeModal() {
        const modal = document.getElementById('addEmployeeModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Attach both click and key events safely
    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('openAddEmployeeBtn');
        if (btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                openModal();
            });
        }
        // Initialize table pagination
        renderPagination();
    });

    window.addEventListener('click', function(event) {
        const modal = document.getElementById('addEmployeeModal');
        if (modal && event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    // Client-side instant canvas image downsampler
    function previewAndCompressPhoto(input, previewImgId, placeholderId, hintId, hiddenInputId) {
        const file = input.files && input.files[0];
        const previewImg = document.getElementById(previewImgId);
        const placeholder = document.getElementById(placeholderId);
        const hint = document.getElementById(hintId);
        const hiddenInput = hiddenInputId ? document.getElementById(hiddenInputId) : null;

        if (!file) {
            if (previewImg) previewImg.style.display = 'none';
            if (placeholder) placeholder.style.display = 'block';
            if (hint) hint.textContent = 'Upload front-facing photo (auto-optimized)';
            if (hiddenInput) hiddenInput.value = '';
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

                const base64Data = canvas.toDataURL('image/jpeg', 0.82);
                if (hiddenInput) {
                    hiddenInput.value = base64Data;
                }

                if (previewImg) {
                    previewImg.src = base64Data;
                    previewImg.style.display = 'block';
                }
                if (placeholder) placeholder.style.display = 'none';

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
                            // Fallback gracefully if DataTransfer not supported
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

    // High performance client-side pagination & live search for 500+ employees
    let currentPage = 1;
    let pageSize = 50;

    function changePageSize(newSize) {
        pageSize = (newSize === 'all') ? 'all' : parseInt(newSize, 10);
        currentPage = 1;
        renderPagination();
    }

    function handleSearchFilter() {
        currentPage = 1;
        renderPagination();
    }

    function getFilteredRows() {
        const searchVal = (document.getElementById('employeeSearch').value || '').trim().toLowerCase();
        const branchVal = (document.getElementById('branchFilter').value || '').trim().toLowerCase();
        const rows = Array.from(document.querySelectorAll('#employeesTable tbody tr.employee-row'));

        return rows.filter(row => {
            const text = row.textContent.toLowerCase();
            const branch = (row.getAttribute('data-branch') || '').toLowerCase();
            const matchSearch = !searchVal || text.indexOf(searchVal) > -1;
            const matchBranch = !branchVal || branch === branchVal;
            return matchSearch && matchBranch;
        });
    }

    function buildPaginationButtons(containerId, totalPages) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '';

        if (totalPages <= 1) return;

        // Prev Button
        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'pagination-btn';
        prevBtn.innerHTML = '‹ Prev';
        prevBtn.disabled = (currentPage === 1);
        prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; renderPagination(); } };
        container.appendChild(prevBtn);

        let startP = Math.max(1, currentPage - 2);
        let endP = Math.min(totalPages, currentPage + 2);

        if (startP > 1) {
            const p1 = document.createElement('button');
            p1.type = 'button';
            p1.className = 'pagination-btn';
            p1.textContent = '1';
            p1.onclick = () => { currentPage = 1; renderPagination(); };
            container.appendChild(p1);
            if (startP > 2) {
                const dots = document.createElement('span');
                dots.textContent = '...';
                dots.style.cssText = 'color:#94a3b8; padding:0 4px;';
                container.appendChild(dots);
            }
        }

        for (let p = startP; p <= endP; p++) {
            const pBtn = document.createElement('button');
            pBtn.type = 'button';
            pBtn.className = 'pagination-btn' + (p === currentPage ? ' active' : '');
            pBtn.textContent = p;
            pBtn.onclick = ((page) => () => { currentPage = page; renderPagination(); })(p);
            container.appendChild(pBtn);
        }

        if (endP < totalPages) {
            if (endP < totalPages - 1) {
                const dots = document.createElement('span');
                dots.textContent = '...';
                dots.style.cssText = 'color:#94a3b8; padding:0 4px;';
                container.appendChild(dots);
            }
            const pLast = document.createElement('button');
            pLast.type = 'button';
            pLast.className = 'pagination-btn';
            pLast.textContent = totalPages;
            pLast.onclick = () => { currentPage = totalPages; renderPagination(); };
            container.appendChild(pLast);
        }

        // Next Button
        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'pagination-btn';
        nextBtn.innerHTML = 'Next ›';
        nextBtn.disabled = (currentPage === totalPages);
        nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; renderPagination(); } };
        container.appendChild(nextBtn);
    }

    function renderPagination() {
        const allRows = document.querySelectorAll('#employeesTable tbody tr.employee-row');
        const matchedRows = getFilteredRows();
        const totalMatched = matchedRows.length;
        const totalAll = allRows.length;

        const limit = (pageSize === 'all') ? totalMatched : parseInt(pageSize, 10);
        const totalPages = (limit > 0) ? Math.ceil(totalMatched / limit) || 1 : 1;

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = (currentPage - 1) * limit;
        const endIdx = (pageSize === 'all') ? totalMatched : Math.min(startIdx + limit, totalMatched);

        allRows.forEach(row => { row.style.display = 'none'; });

        matchedRows.forEach((row, idx) => {
            if (idx >= startIdx && idx < endIdx) {
                row.style.display = '';
            }
        });

        const countText = totalMatched > 0 ? `${startIdx + 1}–${endIdx}` : '0';
        const sc = document.getElementById('showingCount');
        const sbc = document.getElementById('showingBottomCount');
        const tc = document.getElementById('totalCount');
        const tbc = document.getElementById('totalBottomCount');
        if (sc) sc.textContent = countText;
        if (sbc) sbc.textContent = countText;
        if (tc) tc.textContent = totalMatched;
        if (tbc) tbc.textContent = totalMatched;

        const filterNotice = document.getElementById('filterNotice');
        if (filterNotice) {
            filterNotice.style.display = (totalMatched < totalAll) ? 'inline' : 'none';
        }

        const noResultRow = document.getElementById('noResultsRow');
        if (noResultRow) {
            noResultRow.style.display = (totalMatched === 0) ? '' : 'none';
        }

        buildPaginationButtons('paginationTop', totalPages);
        buildPaginationButtons('paginationBottom', totalPages);
    }

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
</script>
</body>
</html>