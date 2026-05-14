<?php

include(__DIR__ . "/../includes/config.php");

// 1. Check Login
if (!isset(<?php

include(__DIR__ . "/../includes/config.php");

// 1. Check Login
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}

// 2. Redirect if no ID
if (!isset($_GET['id']) && !isset($_POST['id'])) {
    header("Location: employees.php");
    exit;
}

// 3. Fetch Employee Data
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $stmt->close();
    
    if (!$employee) {
        die("Employee not found.");
    }
}

// 4. Handle Update Submission
if (isset($_POST['update_employee'])) {
    $id = (int)$_POST['id'];
    $full_name = $_POST['full_name'];
    $job_title = $_POST['job_title'];
    $department = $_POST['department'];
    $branch = $_POST['branch'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    // Check if new photo is uploaded
    if (!empty($_FILES['photo']['name'])) {
        $fileName = time() . "_" . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], "uploads/" . $fileName);
        
        $sql = "UPDATE staff SET full_name=?, job_title=?, department=?, branch=?, email=?, phone=?, photo=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi", $full_name, $job_title, $department, $branch, $email, $phone, $fileName, $id);
    } else {
        $sql = "UPDATE staff SET full_name=?, job_title=?, department=?, branch=?, email=?, phone=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $full_name, $job_title, $department, $branch, $email, $phone, $id);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Updated successfully!'); window.location='employees.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Employee</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>

<div class="edit-container">
    <h2>Edit Employee Information</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
        
        <label>Full Name</label>
        <input type="text" name="full_name" value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>
        
        <label>Job Title</label>
        <input type="text" name="job_title" value="<?php echo htmlspecialchars($employee['job_title']); ?>" required>
        
        <label>Department</label>
        <input type="text" name="department" value="<?php echo htmlspecialchars($employee['department']); ?>" required>
        
        <label>Branch</label>
        <input type="text" name="branch" value="<?php echo htmlspecialchars($employee['branch']); ?>" required>
        
        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
        
        <label>Phone</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($employee['phone']); ?>" required>
        
        <label>Current Photo:</label><br>
        <?php if (!empty($employee['photo'])): ?>
          <img src="uploads/<?php echo $employee['photo']; ?>" class="photo" style="width:60px;height:60px;margin-bottom:10px;" alt="Current photo"><br>
        <?php else: ?>
          <span style="color:var(--text-muted);font-size:13px;">No photo</span><br>
        <?php endif; ?>
        
        <label>Change Photo (optional)</label>
        <input type="file" name="photo" accept="image/*">
        
        <div style="display:flex;gap:12px;margin-top:10px;">
          <button type="submit" name="update_employee">Update Employee</button>
          <a href="employees.php" class="cancel-btn">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>

SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// 2. Redirect if no ID
if (!isset($_GET['id']) && !isset($_POST['id'])) {
    header("Location: employees.php");
    exit;
}

// 3. Fetch Employee Data
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $stmt->close();
    
    if (!$employee) {
        die("Employee not found.");
    }
}

// 4. Handle Update Submission
if (isset($_POST['update_employee'])) {
    $id = (int)$_POST['id'];
    $full_name = $_POST['full_name'];
    $job_title = $_POST['job_title'];
    $department = $_POST['department'];
    $branch = $_POST['branch'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    // Check if new photo is uploaded
    if (!empty($_FILES['photo']['name'])) {
        $fileName = time() . "_" . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], "uploads/" . $fileName);
        
        $sql = "UPDATE staff SET full_name=?, job_title=?, department=?, branch=?, email=?, phone=?, photo=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi", $full_name, $job_title, $department, $branch, $email, $phone, $fileName, $id);
    } else {
        $sql = "UPDATE staff SET full_name=?, job_title=?, department=?, branch=?, email=?, phone=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $full_name, $job_title, $department, $branch, $email, $phone, $id);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Updated successfully!'); window.location='employees.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Employee</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>

<div class="edit-container">
    <h2>Edit Employee Information</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
        
        <label>Full Name</label>
        <input type="text" name="full_name" value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>
        
        <label>Job Title</label>
        <input type="text" name="job_title" value="<?php echo htmlspecialchars($employee['job_title']); ?>" required>
        
        <label>Department</label>
        <input type="text" name="department" value="<?php echo htmlspecialchars($employee['department']); ?>" required>
        
        <label>Branch</label>
        <input type="text" name="branch" value="<?php echo htmlspecialchars($employee['branch']); ?>" required>
        
        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
        
        <label>Phone</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($employee['phone']); ?>" required>
        
        <label>Current Photo:</label><br>
        <?php if (!empty($employee['photo'])): ?>
          <img src="uploads/<?php echo $employee['photo']; ?>" class="photo" style="width:60px;height:60px;margin-bottom:10px;" alt="Current photo"><br>
        <?php else: ?>
          <span style="color:var(--text-muted);font-size:13px;">No photo</span><br>
        <?php endif; ?>
        
        <label>Change Photo (optional)</label>
        <input type="file" name="photo" accept="image/*">
        
        <div style="display:flex;gap:12px;margin-top:10px;">
          <button type="submit" name="update_employee">Update Employee</button>
          <a href="employees.php" class="cancel-btn">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>


