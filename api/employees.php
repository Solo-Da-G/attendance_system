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

    $stmt = $conn->prepare("INSERT INTO staff (staff_id, full_name, job_title, department, branch, email, phone, password, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssssssss", $staff_id, $full_name, $job_title, $department, $branch, $email, $phone, $password, $photo);
        if ($stmt->execute()) {
            echo "<script>alert('Employee added successfully! Default password is: " . addslashes($plain_password) . "'); window.location='employees.php';</script>";
            exit;
        } else {
            echo "<script>alert('Error: " . addslashes($stmt->error) . "');</script>";
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
        <button class="add-btn" onclick="openModal()" style="padding:12px 24px; background:var(--primary); color:white; border:none; border-radius:12px; cursor:pointer;">+ Add New Employee</button>
    </div>

    <div class="search-section">
        <span class="search-icon">🔍</span>
        <input type="text" id="employeeSearch" class="search-input" placeholder="Search by name, staff ID, branch..." onkeyup="filterEmployees()">
    </div>

    <div class="table-wrapper">
        <table class="employees-table" id="employeesTable">
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
                            <td>
                                <?php if ($hasPhoto): ?>
                                    <img src="<?php echo htmlspecialchars($row['photo']); ?>" class="staff-photo" alt="Photo">
                                <?php else: ?>
                                    <div class="no-photo">📷</div>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo htmlspecialchars($row['staff_id']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['job_title'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['department'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['branch'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['email'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['phone'] ?? '—'); ?></td>
                            <td>
                                <a href="edit_employee.php?id=<?php echo $row['id']; ?>">
                                    <button class="action-btn edit-btn">Edit</button>
                                </a>
                                <a href="delete_employee.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this employee?');">
                                    <button class="action-btn delete-btn">Delete</button>
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
<div id="addEmployeeModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="modal-content" style="background:white; padding:32px; border-radius:24px; max-width:500px; width:90%; max-height:90%; overflow-y:auto;">
        <span class="close-btn" onclick="closeModal()" style="float:right; font-size:28px; cursor:pointer;">&times;</span>
        <h3 style="margin-bottom:20px;">Add New Employee</h3>
        <form method="POST" enctype="multipart/form-data">
            <label>Staff ID *</label>
            <input type="text" name="staff_id" placeholder="e.g. EMP001" required style="width:100%; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Full Name *</label>
            <input type="text" name="full_name" placeholder="John Doe" required style="width:100%; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Job Title</label>
            <input type="text" name="job_title" placeholder="Software Engineer" style="width:100%; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Department</label>
            <input type="text" name="department" placeholder="IT Department" style="width:100%; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Branch</label>
            <?php if (!empty($branch_options)): ?>
            <select name="branch" style="width:100%; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">
                <option value="">Select branch</option>
                <?php foreach ($branch_options as $bn): ?>
                <option value="<?php echo htmlspecialchars($bn); ?>"><?php echo htmlspecialchars($bn); ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="text" name="branch" placeholder="Add branches first" style="width:100%; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">
            <?php endif; ?>

            <label>Email</label>
            <input type="email" name="email" placeholder="john@example.com" style="width:100%; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Mobile No</label>
            <input type="text" name="phone" placeholder="+234..." style="width:100%; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">

            <label>Password (Optional)</label>
            <input type="password" name="password" placeholder="Leave blank to use Staff ID" style="width:100%; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">
            <small style="color:var(--text-muted); display:block; margin-bottom:16px;">Default: Staff ID</small>

            <label>Profile Photo (Required for Face Recognition)</label>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/jpg" style="width:100%; padding:12px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px;">
            <small style="color:var(--text-muted); display:block; margin-bottom:16px;">Upload a clear front-facing photo for face verification</small>

            <button type="submit" name="add_employee" style="width:100%; padding:14px; background:var(--primary); color:white; border:none; border-radius:12px; font-weight:700; cursor:pointer;">Add Employee</button>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('addEmployeeModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('addEmployeeModal').style.display = 'none';
    }

    window.onclick = function(event) {
        let modal = document.getElementById('addEmployeeModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

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
</script>
</body>
</html>