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
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
        $imgData = file_get_contents($_FILES['photo']['tmp_name']);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $imgType = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);
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

// Get all employees (including those with photos)
$result = $conn->query("SELECT * FROM staff WHERE deleted_at IS NULL ORDER BY id DESC");
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
</style>
</head>
<body class="app-page employees-page">

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
        <div>
            <h2>📋 Employees</h2>
            <p class="subtitle">Manage staff records and profile photos for face recognition</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="backup.php?action=employees_excel" target="_blank" class="export-btn" style="padding:12px 20px; background:linear-gradient(135deg, #10b981, #059669); color:white; border:none; border-radius:12px; text-decoration:none; font-weight:700; font-size:14px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 10px rgba(16,185,129,0.3);">
                <span>📊</span> Export to Excel
            </a>
            <button type="button" id="openAddEmployeeBtn" class="add-btn" onclick="openModal()" style="padding:12px 24px; background:var(--primary); color:white; border:none; border-radius:12px; cursor:pointer; font-weight:700; font-size:14px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 10px rgba(37,99,235,0.25);">+ Add New Employee</button>
        </div>
    </div>

    <div class="search-section">
        <span class="search-icon">🔍</span>
        <input type="text" id="employeeSearch" class="search-input" placeholder="Search by name, staff ID, branch..." onkeyup="filterEmployees()">
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
                        $hasPhoto = !empty($row['photo']) && strlen($row['photo']) > 500 && strpos($row['photo'], 'data:image') === 0;
                    ?>
                        <tr>
                            <td data-label="Photo">
                                <?php if ($hasPhoto): ?>
                                    <img src="<?php echo htmlspecialchars($row['photo']); ?>" class="staff-photo" alt="Photo">
                                <?php else: ?>
                                    <div class="no-photo">📷</div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Staff ID"><code><?php echo htmlspecialchars($row['staff_id']); ?></code></td>
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
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding:40px;">No employees found. Click "Add New Employee" to get started.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
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

            <label>Profile Photo (Required for Face Recognition)</label>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/jpg" style="width:100%; box-sizing:border-box; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">
            <small style="color:var(--text-muted); display:block; margin-bottom:16px;">Upload a clear front-facing photo for face verification</small>

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

    function filterEmployees() {
        const input = document.getElementById('employeeSearch');
        const filter = (input.value || '').toUpperCase();
        const table = document.getElementById('employeesTable');
        const rows = table.getElementsByTagName('tr');
        for (let i = 1; i < rows.length; i++) {
            const tds = rows[i].getElementsByTagName('td');
            let found = false;
            for (let j = 0; j < tds.length; j++) {
                if ((tds[j].textContent || '').toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
            rows[i].style.display = found ? '' : 'none';
        }
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