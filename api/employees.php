<?php
include(__DIR__ . "/../includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle form submission (Add Employee)
if (isset($_POST['add_employee'])) {
    $staff_id = trim($_POST['staff_id']);
    $full_name = trim($_POST['full_name']);
    $job_title = trim($_POST['job_title']);
    $department = trim($_POST['department']);
    $branch = trim($_POST['branch']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    $photo = "";

    // Handle photo upload — stored as Base64 in DB (Vercel is read-only)
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
        $imgData = file_get_contents($_FILES['photo']['tmp_name']);
        $imgType = mime_content_type($_FILES['photo']['tmp_name']);
        $photo   = 'data:' . $imgType . ';base64,' . base64_encode($imgData);
    }

    // Insert new record
    $stmt = $conn->prepare("INSERT INTO staff (staff_id, full_name, job_title, department, branch, email, phone, password, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssss", $staff_id, $full_name, $job_title, $department, $branch, $email, $phone, $password, $photo);

    if ($stmt->execute()) {
        echo "<script>alert('Employee added successfully!'); window.location='employees.php';</script>";
    } else {
        echo "<script>alert('Error adding employee. Please try again.');</script>";
    }
    $stmt->close();
}

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
</head>
<body>

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<!-- Content -->
<div class="content">
  <h2>Employees Management</h2>

  <button class="add-btn" onclick="openModal()">+ Add New Employee</button>

  <!-- Table -->
  <table>
    <tr>
      <th>Photo</th>
      <th>Name</th>
      <th>Employee ID</th>
      <th>Job Title</th>
      <th>Department</th>
      <th>Branch</th>
      <th>Email</th>
      <th>Mobile</th>
      <th>Actions</th>
    </tr>

    <?php
    $result = $conn->query("SELECT * FROM staff ORDER BY id DESC");
    if ($result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
        echo "<tr>
          <td>";
          if (!empty($row['photo'])) {
            // Photo stored as Base64 data URI
            echo "<img src='{$row['photo']}' class='photo' alt='Photo' style='width:40px;height:40px;border-radius:50%;object-fit:cover;'>";
          } else {
            echo "<span style='color:var(--text-muted);font-size:13px;'>No photo</span>";
          }
        echo "</td>
          <td><strong>" . htmlspecialchars($row['full_name']) . "</strong></td>
          <td>{$row['staff_id']}</td>
          <td>{$row['job_title']}</td>
          <td>{$row['department']}</td>
          <td>{$row['branch']}</td>
          <td>{$row['email']}</td>
          <td>{$row['phone']}</td>
          <td>
            <a href='edit_employee.php?id={$row['id']}'><button class='action-btn edit-btn'>Edit</button></a>
            <a href='delete_employee.php?id={$row['id']}' onclick='return confirm(\"Are you sure?\");'><button class='action-btn delete-btn'>Delete</button></a>
          </td>
        </tr>";
      }
    } else {
      echo "<tr><td colspan='9' style='text-align:center;'>No employees found</td></tr>";
    }
    ?>
  </table>

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
      <label>Employee ID</label>
      <input type="text" name="staff_id" placeholder="e.g. EMP001" required>
      <label>Full Name</label>
      <input type="text" name="full_name" placeholder="John Doe" required>
      <label>Job Title</label>
      <input type="text" name="job_title" placeholder="Software Engineer" required>
      <label>Department</label>
      <input type="text" name="department" placeholder="IT Department" required>
      <label>Branch</label>
      <?php if (!empty($branch_options)): ?>
      <select name="branch" required>
        <option value="">Select branch (must match Manage Branches)</option>
        <?php foreach ($branch_options as $bn): ?>
        <option value="<?php echo htmlspecialchars($bn); ?>"><?php echo htmlspecialchars($bn); ?></option>
        <?php endforeach; ?>
      </select>
      <?php else: ?>
      <input type="text" name="branch" placeholder="Add branches first in Manage Branches" required>
      <?php endif; ?>
      <label>Email</label>
      <input type="email" name="email" placeholder="john@example.com" required>
      <label>Mobile No</label>
      <input type="text" name="phone" placeholder="+234..." required>
      <label>Password (Login)</label>
      <input type="password" name="password" placeholder="Defaults to Staff ID if blank">
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
</script>
</body>
</html>


