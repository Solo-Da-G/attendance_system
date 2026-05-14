<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Debug Mode</h2>";

// 1. Check Config File
if (file_exists(__DIR__ . "/../includes/config.php")) {
    echo "✅ includes/config.php exists<br>";
} else {
    echo "❌ includes/config.php MISSING<br>";
}

// 2. Try DB Connection
include(__DIR__ . "/../includes/config.php");

if (isset($conn) && $conn->ping()) {
    echo "✅ Database Connection OK<br>";
} else {
    echo "❌ Database Connection FAILED<br>";
}

// 3. Check Session
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Session Started<br>";
} else {
    echo "❌ Session NOT Started<br>";
}

echo "<h3>Environment:</h3>";
echo "VERCEL: " . (getenv('VERCEL') ? "YES" : "NO") . "<br>";
echo "DB_HOST: " . getenv('DB_HOST') . "<br>";

echo "<h3>PHP Version:</h3>";
echo phpversion();
?>
