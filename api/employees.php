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

    // If password is empty, default to Staff ID
    $plain_password = !empty($_POST['password']) ? $_POST['password'] : $staff_id;
    $password       = password_hash($plain_password, PASSWORD_DEFAULT);

    $photo = "";
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
        $imgData = file_get_contents($_FILES['photo']['tmp_name']);
        $imgType = mime_content_type($_FILES['photo']['tmp_name']);
        $photo   = 'data:' . $imgType . ';base64,' . base64_encode($imgData);
    }

    $stmt = $conn->prepare("INSERT INTO staff (staff_id, full_name, job_title, department, branch, email, phone, password, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssssssss", $staff_id, $full_name, $job_title, $department, $branch, $email, $phone, $password, $photo);
        if ($stmt->execute()) {
            echo "<script>alert('Employee added successfully! Default password is: " . addslashes($plain_password) . "'); window.location='employees.php';</script>";
            exit;
        } else {
            echo "<script>alert('Error adding employee: " . addslashes($stmt->error) . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Server error: could not prepare statement.');</script>";
    }
}

// Branch options
$branch_options = [];
$br_res = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name ASC");
if ($br_res) {
    while ($br = $br_res->fetch_assoc()) {
        $branch_options[] = $br['branch_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employees</title>
<link rel="stylesheet" href="/asset/css/style.css">
<style>
    .password-hint {
        background: rgba(254, 243, 199, 0.9);
        border: 1px solid rgba(245, 158, 11, 0.35);
        border-left: 5px solid #f59e0b;
        padding: 14px 16px;
        margin: 14px 0 18px;
        border-radius: 14px;
        font-size: 13px;
        color: #92400e;
        box-shadow: var(--shadow-sm);
        backdrop-filter: blur(10px);
    }
</style>
</head>
<body class="app-page employees-page">

<?php include(__DIR__ . "/includes/sidebar.php"); ?>

<div class="content">
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap;">
    <div>
      <h2>Employees</h2>
      <p class="subtitle">Manage staff records, photos, and default passwords.</p>
    </div>
    <button class="add-btn" onclick="openModal()">+ Add New Employee</button>
  </div>

  <div class="search-section" style="margin-top: 12px;">
    <span class="search-icon">🔍</span>
    <input type="text" id="employeeSearch" class="search-input" placeholder="Search staff by name, phone, staff ID, branch..." onkeyup="filterEmployees()">
  </div>

  <div class="password-hint">
    💡 <strong>Note:</strong> If you leave the password blank, the employee's default password will be their <strong>Staff ID</strong>.
  </div>

  <div class="table-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
      <div style="font-weight:800;">Staff List</div>
      <div style="color:var(--text-muted);font-size:13px;">Tip: Hover a row to highlight it.</div>
    </div>

    <div style="overflow:auto;max-width:100%;">
      <table class="responsive-table pro-table" id="employeesTable">
        <colgroup>
          <col style="width: 60px;">
          <col style="width: 84px;">
          <col style="width: 220px;">
          <col style="width: 110px;">
          <col style="width: 170px;">
          <col style="width: 150px;">
          <col style="width: 190px;">
          <col style="width: 220px;">
          <col style="width: 130px;">
          <col style="width: 150px;">
        </colgroup>
        <thead>
          <tr>
            <th>ID</th>
            <th>Photo</th>
            <th>Name</th>
            <th>Staff ID</th>
            <th>Job Title</th>
            <th>Department</th>
            <th>Branch</th>
            <th>Email</th>
            <th>Mobile</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $result = $conn->query("SELECT * FROM staff WHERE deleted_at IS NULL ORDER BY id DESC");
        if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            $fullName = htmlspecialchars($row['full_name']);
            $staffId  = htmlspecialchars($row['staff_id']);
            $jobTitle = htmlspecialchars($row['job_title']);
            $dept     = htmlspecialchars($row['department']);
            $branch   = htmlspecialchars($row['branch']);
            $email    = htmlspecialchars($row['email']);
            $phone    = htmlspecialchars($row['phone']);

            echo "<tr>
              <td data-label='ID'>{$row['id']}</td>
              <td data-label='Photo'>";
              if (!empty($row['photo'])) {
                echo "<img src='{$row['photo']}' class='photo' alt='Photo'>";
              } else {
                echo "<span style='color:var(--text-muted);font-size:13px;'>No photo</span>";
              }
            echo "</td>
              <td data-label='Name' title='{$fullName}'><strong>{$fullName}</strong></td>
              <td data-label='Staff ID' title='{$staffId}'><code>{$staffId}</code></td>
              <td data-label='Job Title' title='{$jobTitle}'>{$jobTitle}</td>
              <td data-label='Department' title='{$dept}'>{$dept}</td>
              <td data-label='Branch' title='{$branch}'>{$branch}</td>
              <td data-label='Email' title='{$email}'>{$email}</td>
              <td data-label='Mobile' title='{$phone}'>{$phone}</td>
              <td data-label='Actions' style='white-space:nowrap;'>
                <a href='edit_employee.php?id={$row['id']}'><button class='action-btn edit-btn'>Edit</button></a>
                <a href='delete_employee.php?id={$row['id']}' onclick='return confirm(\"Are you sure?\");'><button class='action-btn delete-btn'>Delete</button></a>
              </td>
            </tr>";
          }
        } else {
          echo "<tr><td colspan='10' style='text-align:center;color:var(--text-muted);'>No employees found</td></tr>";
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="footer">
    &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Mbewu
  </div>
</div>

<!-- Add Employee Modal -->
<div id="addEmployeeModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeModal()">&times;</span>
    <h3>Add New Employee</h3>
    <form method="POST" enctype="multipart/form-data">
      <label>Staff ID *</label>
      <input type="text" name="staff_id" placeholder="e.g. EMP001" required>
      <small style="color:var(--text-muted);">This will be the default password if left blank</small>

      <label>Full Name *</label>
      <input type="text" name="full_name" placeholder="John Doe" required>

      <label>Job Title</label>
      <input type="text" name="job_title" placeholder="Software Engineer" required>

      <label>Department</label>
      <input type="text" name="department" placeholder="IT Department" required>

      <label>Branch</label>
      <?php if (!empty($branch_options)): ?>
      <select name="branch" required>
        <option value="">Select branch</option>
        <?php foreach ($branch_options as $bn): ?>
        <option value="<?php echo htmlspecialchars($bn); ?>"><?php echo htmlspecialchars($bn); ?></option>
        <?php endforeach; ?>
      </select>
      <?php else: ?>
      <input type="text" name="branch" placeholder="Add branches first" required>
      <?php endif; ?>

      <label>Email</label>
      <input type="email" name="email" placeholder="john@example.com" required>

      <label>Mobile No</label>
      <input type="text" name="phone" placeholder="+234..." required>

      <label>Password (Optional)</label>
      <input type="password" name="password" placeholder="Leave blank to use Staff ID">
      <small style="color:var(--text-muted);">Default: Staff ID</small>

      <label>Photo</label>
      <input type="file" name="photo" accept="image/*">

      <button type="submit" name="add_employee">Add Employee</button>
    </form>
  </div>
</div>

<script>
  function openModal() {
    document.getElementById('addEmployeeModal').style.display = 'block';
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
        if ((tds[j].textContent || '').toUpperCase().indexOf(filter) > -1) { found = true; break; }
      }
      rows[i].style.display = found ? '' : 'none';
    }
  }
</script>
<script src="/asset/js/ui-enhancements.js" defer></script>
</body>
</html>
