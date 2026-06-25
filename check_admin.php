<?php
include(__DIR__ . "/api/includes/config.php");
$res = $conn->query("SELECT * FROM admin");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
$res = $conn->query("SELECT * FROM staff");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
