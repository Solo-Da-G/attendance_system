<?php

include(__DIR__ . "/../includes/config.php");

// Auth check
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: employees.php");
    exit;
}

header("Location: employees.php");
?>
