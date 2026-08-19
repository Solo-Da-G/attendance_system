<?php
ob_start();

/**
 * Vercel Hobby Plan Router
 *
 * Routes all incoming PHP requests to the correct file inside /api/.
 * Single Serverless Function - stays within Vercel Hobby plan limit of 12.
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$page = trim($path, '/');

// Strip .php extension if present
if (substr($page, -4) === '.php') {
    $page = substr($page, 0, -4);
}

// Strip leading "api/" prefix so /api/logout works the same as /logout
if (strpos($page, 'api/') === 0) {
    $page = substr($page, 4);
}

// Default to login page
if ($page === '' || $page === 'index') {
    $page = 'index';
}

// Build target file path
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
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f1f5f9;}
    .box{text-align:center;padding:40px;background:#fff;border-radius:16px;box-shadow:0 4px 30px rgba(0,0,0,.1);}
    h1{color:#0f172a;}p{color:#64748b;}a{color:#10439f;font-weight:700;}</style></head>';
    echo '<body><div class="box"><h1>404</h1><p>Page not found.</p><a href="/">Go to Login</a></div></body></html>';
}