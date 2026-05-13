<?php
include(__DIR__ . "/../includes/config.php");

if (!isset($_SESSION['test_value'])) {
    $_SESSION['test_value'] = "Memory is working! " . date('H:i:s');
    echo "<h1>Session NOT found</h1>";
    echo "<p>I have now set a test value. Please <b>Refresh</b> the page to see if I remember it.</p>";
} else {
    echo "<h1>✅ Session FOUND!</h1>";
    echo "<p>Value: " . $_SESSION['test_value'] . "</p>";
    echo "<p>This means Vercel's memory is working perfectly.</p>";
}

echo "<hr><p>Debug Info:</p>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Path: " . session_save_path() . "<br>";
echo "Environment: " . ENVIRONMENT;
?>
