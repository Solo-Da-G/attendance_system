<?php
/**
 * TDS Attendance System - Android APK Download & Fallback Controller
 */
$apkRelative = '/asset/app/TDS_Attendance.apk';
$apkPath = dirname(__DIR__) . $apkRelative;

if (file_exists($apkPath) && filesize($apkPath) > 1000) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="TDS_Attendance.apk"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($apkPath));
    readfile($apkPath);
    exit;
}

// Fallback user-facing guide page if APK is being prepared
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download TDS Attendance App</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 24px 16px;
            background: #060d1f;
            color: #f8fafc;
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .card {
            background: #0f172a;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px;
            padding: 32px 24px;
            max-width: 440px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .logo-box {
            width: 68px;
            height: 68px;
            background: #ffffff;
            border-radius: 18px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            box-sizing: border-box;
        }
        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        h1 {
            font-size: 20px;
            font-weight: 800;
            margin: 0 0 8px;
        }
        p {
            font-size: 13.5px;
            color: #94a3b8;
            line-height: 1.5;
            margin: 0 0 20px;
        }
        .step-box {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 16px;
            text-align: left;
            margin-bottom: 20px;
            font-size: 13px;
            color: #e2e8f0;
            line-height: 1.6;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background: #10439f;
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-box">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo">
        </div>
        <h1>TDS Attendance App</h1>
        <p>The Android APK package is being prepared. In the meantime, you can easily install the app directly in your browser:</p>
        
        <div class="step-box">
            <strong>How to install via Google Chrome:</strong><br><br>
            1. Tap the <strong>three dots (⋮)</strong> at the top right of Chrome.<br>
            2. <strong>Scroll DOWN</strong> past <em>'Translate'</em>.<br>
            3. Tap <strong>'Add to Home screen'</strong> or <strong>'Install app'</strong>.<br><br>
            <em style="color:#facc15; font-size:12px;">POCO/Xiaomi users: Ensure 'Lock Home screen layout' is OFF in your phone home screen settings.</em>
        </div>

        <a href="/dashboard.php" class="btn">Return to Dashboard</a>
    </div>
</body>
</html>