<?php
ob_start();

/**
 * Vercel Hobby Plan Router
 *
 * Routes all incoming PHP requests to the correct file inside /api/.
 * This allows the entire project to use a SINGLE Serverless Function,
 * staying within Vercel's free-tier limit of 12 functions.
 *
 * ob_start() is called first to prevent "headers already sent" errors
 * caused by any BOM or whitespace before <?php.
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$page = trim($path, '/');

if (substr($page, -4) === '.php') {
    $page = substr($page, 0, -4);
}

if ($page === '' || $page === 'index') {
    $page = 'index';
}

$targetFile = __DIR__ . '/' . $page . '.php';
$realTarget = realpath($targetFile);
$realApiDir = realpath(__DIR__);

if (
    $realTarget !== false &&
    strpos($realTarget, $realApiDir) === 0 &&
    file_exists($realTarget)
) {
    include $realTarget;
} else {
    ob_end_clean();
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head>';
    echo '<body><h1>404 - Page Not Found</h1>';
    echo '<p>The page <strong>' . htmlspecialchars($page) . '</strong> does not exist.</p>';
    echo '<p><a href="/">Go to Login</a></p></body></html>';
}