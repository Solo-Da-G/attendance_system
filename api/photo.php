<?php
/**
 * Lightweight On-Demand Photo Delivery Endpoint
 *
 * Prevents Vercel 500 FUNCTION_RESPONSE_PAYLOAD_TOO_LARGE by streaming
 * photos individually as binary assets with HTTP caching instead of
 * embedding large base64 data URIs into page HTML.
 *
 * Usage:
 *   /api/photo.php?type=attendance&id=123
 *   /api/photo.php?type=staff&id=TDS-001
 */

// Buffer cleanup to prevent whitespace corruption of binary images
if (ob_get_level()) {
    ob_end_clean();
}

include(__DIR__ . "/includes/config.php");

$type = $_GET['type'] ?? '';
$id   = $_GET['id'] ?? '';

function render_placeholder($emoji = '👤') {
    header("Content-Type: image/svg+xml");
    header("Cache-Control: public, max-age=86400");
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">
        <rect width="120" height="120" rx="16" fill="#f1f5f9"/>
        <text x="60" y="68" font-family="Segoe UI Emoji, Apple Color Emoji, sans-serif" font-size="36" text-anchor="middle">' . $emoji . '</text>
    </svg>';
    exit;
}

if (empty($type) || empty($id)) {
    render_placeholder();
}

$rawPhoto = '';

if ($type === 'attendance') {
    $attId = (int)$id;
    if ($attId <= 0) render_placeholder('📷');

    $stmt = $conn->prepare("SELECT photo_in, photo_out FROM attendance WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $attId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $rawPhoto = !empty($row['photo_in']) ? $row['photo_in'] : ($row['photo_out'] ?? '');
        }
        $stmt->close();
    }
} elseif ($type === 'staff') {
    $staffId = trim((string)$id);
    if ($staffId === '') render_placeholder('👤');

    $stmt = $conn->prepare("SELECT photo FROM staff WHERE staff_id = ? OR (id = ? AND ? > 0) LIMIT 1");
    if ($stmt) {
        $numId = is_numeric($staffId) ? (int)$staffId : 0;
        $stmt->bind_param("sii", $staffId, $numId, $numId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $rawPhoto = $row['photo'] ?? '';
        }
        $stmt->close();
    }
}

if (empty($rawPhoto)) {
    render_placeholder($type === 'attendance' ? '📷' : '👤');
}

// Check if rawPhoto is a data URI: data:[mime];base64,[data]
if (preg_match('#^data:(image/[a-zA-Z0-9\+\-\.]+);base64,(.+)$#s', $rawPhoto, $matches)) {
    $mimeType = $matches[1];
    $binary   = base64_decode($matches[2]);

    if ($binary !== false) {
        // If image is larger than 180KB, downsample if GD is available to prevent serverless payload overflow
        if (strlen($binary) > 180 * 1024 && extension_loaded('gd') && function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring($binary);
            if ($src !== false) {
                $w = imagesx($src);
                $h = imagesy($src);
                $maxDim = 400;
                if ($w > $maxDim || $h > $maxDim) {
                    $ratio = min($maxDim / $w, $maxDim / $h);
                    $nw = max(1, (int)round($w * $ratio));
                    $nh = max(1, (int)round($h * $ratio));
                    $dst = imagecreatetruecolor($nw, $nh);
                    if ($mimeType === 'image/png') {
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        ob_start();
                        imagepng($dst, null, 7);
                        $compressed = ob_get_clean();
                    } else {
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        ob_start();
                        imagejpeg($dst, null, 80);
                        $compressed = ob_get_clean();
                    }
                    imagedestroy($dst);
                    imagedestroy($src);
                    if (!empty($compressed) && strlen($compressed) < strlen($binary)) {
                        $binary = $compressed;
                    }
                } else {
                    imagedestroy($src);
                }
            }
        }

        header("Content-Type: " . $mimeType);
        header("Cache-Control: public, max-age=86400, stale-while-revalidate=604800");
        header("Content-Length: " . strlen($binary));
        echo $binary;
        exit;
    }
}

// Fallback if stored as a direct URL or local asset
if (str_starts_with($rawPhoto, 'http://') || str_starts_with($rawPhoto, 'https://') || str_starts_with($rawPhoto, '/')) {
    header("Location: " . $rawPhoto, true, 302);
    exit;
}

render_placeholder($type === 'attendance' ? '📷' : '👤');
