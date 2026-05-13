<?php
include(__DIR__ . "/../includes/config.php");
session_unset();
session_destroy();
echo "<script>window.location.href='/index.php';</script>";
exit;
?>
