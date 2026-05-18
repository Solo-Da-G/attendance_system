<?php
include(__DIR__ . "/../includes/config.php");

if (!isset($_SESSION['admin_id'])) {
    die("Admin login required");
}

echo "<h2>Debug: Employee Update Test</h2>";

// Test direct update
if (isset($_POST['test_update'])) {
    $id = (int)$_POST['id'];
    $new_password = $_POST['test_password'];
    
    echo "<pre>";
    echo "Attempting to update employee ID: $id<br>";
    echo "New password (plain): $new_password<br>";
    
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    echo "Hashed password: $hashed<br>";
    
    $sql = "UPDATE staff SET password = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo "ERROR preparing statement: " . $conn->error . "<br>";
    } else {
        $stmt->bind_param("si", $hashed, $id);
        if ($stmt->execute()) {
            echo "✅ SUCCESS! Password updated.<br>";
        } else {
            echo "❌ ERROR executing: " . $stmt->error . "<br>";
        }
        $stmt->close();
    }
    echo "</pre>";
}

// Show current staff
$result = $conn->query("SELECT id, staff_id, full_name, 
    CASE WHEN password IS NULL THEN 'NULL' 
         WHEN password = '' THEN 'EMPTY' 
         ELSE 'HASHED (' . LENGTH(password) . ' chars)' 
    END as pass_status 
    FROM staff LIMIT 20");

echo "<h3>Current Staff Records:</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
echo "<tr style='background:#f1f5f9;'><th>ID</th><th>Staff ID</th><th>Name</th><th>Password Status</th><th>Action</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['staff_id']}</td>";
    echo "<td>{$row['full_name']}</td>";
    echo "<td>{$row['pass_status']}</td>";
    echo "<td>
            <form method='POST' style='margin:0'>
                <input type='hidden' name='id' value='{$row['id']}'>
                <input type='text' name='test_password' placeholder='New password' required size='12'>
                <button type='submit' name='test_update'>Update</button>
            </form>
          </td>";
    echo "</tr>";
}
echo "</table>";

echo "<p style='margin-top:20px;'><strong>Instructions:</strong><br>
1. Try updating a staff password using the form above<br>
2. If it works, then the main edit_employee.php should also work<br>
3. <strong>DELETE THIS FILE AFTER TESTING!</strong></p>";
echo "<a href='employees.php'>← Back to Employees</a>";
?>