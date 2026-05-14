<?php
include(__DIR__ . "/../includes/config.php");

// Auth check
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Redirect if no ID provided
if (!isset($_GET['id']) && !isset($_POST['id'])) {
    header("Location: employees.php");
    exit;
}

// Fetch employee data
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

// Handle update form submission
if (isset($_POST['update_employee'])) {
    $id         = (int)$_POST['id'];
    $full_name  = $_POST['full_name'];
    $job_title  = $_POST['job_title'];
    $department = $_POST['department'];
    $branch     = $_POST['branch'];
    $email      = $_POST['email'];
    $phone      = $_POST['phone'];
    $finger_id  = $_POST['fingerprint_id'];
    $new_pass   = $_POST['new_password'];

    $params = [$full_name, $job_title, $department, $branch, $email, $phone, $finger_id];
    $types  = "sssssss";

    $sql = "UPDATE staff SET full_name=?, job_title=?, department=?, branch=?, email=?, phone=?, fingerprint_id=?";

    // Handle password update if provided
    if (!empty($new_pass)) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $sql .= ", password=?";
        $params[] = $hashed;
        $types .= "s";
    }

    // Handle photo update
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
        $imgData  = file_get_contents($_FILES['photo']['tmp_name']);
        $imgType  = mime_content_type($_FILES['photo']['tmp_name']);
        $photo    = 'data:' . $imgType . ';base64,' . base64_encode($imgData);
        $sql .= ", photo=?";
        $params[] = $photo;
        $types .= "s";
    }

    $sql .= " WHERE id=?";
    $params[] = $id;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo "<script>alert('Updated successfully!'); window.location='/employees.php';</script>";
    } else {
        echo "<script>alert('Error updating employee.');</script>";
    }
    $stmt->close();
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

<?php include(__DIR__ . "/../includes/sidebar.php"); ?>

<div class="content">
    <h2>Edit Employee</h2>

    <form method="POST" enctype="multipart/form-data" style="max-width:600px;">
        <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">

        <label>Full Name</label>
        <input type="text" name="full_name" value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>

        <label>Job Title</label>
        <input type="text" name="job_title" value="<?php echo htmlspecialchars($employee['job_title']); ?>">

        <label>Department</label>
        <input type="text" name="department" value="<?php echo htmlspecialchars($employee['department']); ?>">

        <label>Branch</label>
        <input type="text" name="branch" value="<?php echo htmlspecialchars($employee['branch']); ?>">

        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>">

        <label>Phone</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($employee['phone']); ?>">

        <label>Fingerprint User ID (ZKTeco)</label>
        <input type="text" name="fingerprint_id" value="<?php echo htmlspecialchars($employee['fingerprint_id']); ?>" placeholder="e.g. 1">

        <label>New Password (leave blank to keep current)</label>
        <input type="password" name="new_password" placeholder="Set a new login password">

        <label>Current Photo</label><br>
        <?php if (!empty($employee['photo'])): ?>
            <img src="<?php echo $employee['photo']; ?>" style="width:70px;height:70px;border-radius:50%;object-fit:cover;margin-bottom:10px;" alt="Current photo"><br>
        <?php else: ?>
            <span style="color:var(--text-muted);font-size:13px;">No photo uploaded</span><br>
        <?php endif; ?>

        <label>Change Photo (optional)</label>
        <input type="file" name="photo" accept="image/*">

        <div style="display:flex;gap:12px;margin-top:25px;">
            <button type="submit" name="update_employee">Save Changes</button>
            <a href="/employees.php"><button type="button" style="background:#f1f5f9; color:#475569;">Cancel</button></a>
        </div>
    </form>

    <div class="footer">&copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Collins</div>
</div>

</body>
</html>
