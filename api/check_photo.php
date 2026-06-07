<?php
include(__DIR__ . "/includes/config.php");

if (!isset($_SESSION['staff_id']) && !isset($_SESSION['admin_id'])) {
    die("Please login first");
}

echo "<h2>Photo Debug</h2>";

if (isset($_SESSION['staff_id'])) {
    $staff_id = $_SESSION['staff_id'];
    echo "<h3>Checking Staff: $staff_id</h3>";
    
    $stmt = $conn->prepare("SELECT id, staff_id, full_name, photo FROM staff WHERE staff_id = ?");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_assoc();
    
    if ($staff) {
        echo "<p>Staff ID: " . htmlspecialchars($staff['staff_id']) . "</p>";
        echo "<p>Full Name: " . htmlspecialchars($staff['full_name']) . "</p>";
        
        if (!empty($staff['photo'])) {
            $photo_len = strlen($staff['photo']);
            echo "<p>Photo exists! Length: $photo_len bytes</p>";
            
            if ($photo_len > 500 && strpos($staff['photo'], 'data:image') === 0) {
                echo "<p style='color:green'>✅ Photo format is valid (data:image)</p>";
                echo "<img src='" . htmlspecialchars($staff['photo']) . "' style='max-width:200px; border-radius:50%;'>";
            } else {
                echo "<p style='color:red'>❌ Photo format is INVALID. Expected data:image but got: " . substr($staff['photo'], 0, 50) . "</p>";
            }
        } else {
            echo "<p style='color:red'>❌ NO PHOTO FOUND in database!</p>";
            echo "<p>Please go to Employees page and upload a photo for this staff member.</p>";
        }
    } else {
        echo "<p style='color:red'>Staff not found!</p>";
    }
    $stmt->close();
}
?>