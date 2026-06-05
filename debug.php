<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Debug Mode</h2>";

// 1. Check Extensions
if (!extension_loaded('mysqli')) {
    die("❌ Error: 'mysqli' extension is NOT loaded. This is why the site is 500ing.");
} else {
    echo "✅ mysqli extension is loaded<br>";
}

// 2. Check Config File
if (file_exists(__DIR__ . "/includes/config.php")) {
    echo "✅ includes/config.php exists<br>";
} else {
    echo "❌ includes/config.php MISSING<br>";
}

// 2. Try DB Connection
include(__DIR__ . "/includes/config.php");

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

echo "<h3>Environment Variables Check:</h3>";
$vars = ['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'];
foreach ($vars as $v) {
    $val = getenv($v);
    if ($val) {
        $masked = ($v === 'DB_PASSWORD') ? '********' : $val;
        echo "✅ $v: $masked<br>";
    } else {
        echo "❌ $v: MISSING<br>";
    }
}

echo "<h3>PHP Info:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Interface: " . php_sapi_name() . "<br>";
?>
