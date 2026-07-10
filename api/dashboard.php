<?php
include(__DIR__ . "/includes/config.php");

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

$today_date = date("Y-m-d");
// Auto-close any stale open attendance (previous days) so they show as "Missed clock-out" from 12:00am.
try {
    $stale = $conn->prepare("SELECT id, clock_in FROM attendance WHERE clock_out IS NULL AND DATE(clock_in) < CURDATE() ORDER BY id DESC LIMIT 50");
    if ($stale) {
        $stale->execute();
        $rs = $stale->get_result();
        while ($r = $rs->fetch_assoc()) {
            $clock_in_date = date("Y-m-d", strtotime($r['clock_in']));
            $midnight = date("Y-m-d 00:00:00", strtotime($clock_in_date . " +1 day"));
            $upd = $conn->prepare("UPDATE attendance SET clock_out = ?, status = 'missed_out', total_hours = 0 WHERE id = ? AND clock_out IS NULL");
            if ($upd) {
                $upd->bind_param("si", $midnight, $r['id']);
                $upd->execute();
                $upd->close();
            }
        }
        $stale->close();
    }
} catch (Throwable $t) { /* ignore */ }

$is_admin = isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin');
$staff_id = $_SESSION['staff_id'] ?? null;
$display_name = $_SESSION['admin'] ?? 'User';
$staff_branch_name = '';
$staff_photo_ok = false;
$staff_job_title = '';
$staff_department = '';
$staff_photo = '';
$staff_photo_error = '';
$style_version = @filemtime(dirname(__DIR__) . "/asset/css/style.css") ?: time();
$face_api_version = @filemtime(dirname(__DIR__) . "/asset/js/face-api.min.js") ?: time();
$clock_face_version = @filemtime(dirname(__DIR__) . "/asset/js/clock-face.js") ?: time();
$idle_logout_version = @filemtime(dirname(__DIR__) . "/asset/js/idle-logout.js") ?: time();
$th = 0;
$th_today = 0;
$th_yest = 0;

if (!function_exists('formatHours')) {
    function formatHours($decimal) {
        if (!$decimal) return "0 hrs 0 min";
        $h = floor($decimal);
        $m = floor(($decimal - $h) * 60);
        $s = round((($decimal - $h) * 60 - $m) * 60);
        $str = "";
        if ($h > 0) $str .= "{$h}hrs ";
        $str .= "{$m}min";
        if ($s > 0) $str .= " {$s}sec";
        return trim($str);
    }
}

if ($staff_id) {
    $photo_stmt = $conn->prepare("SELECT photo, branch, job_title, department FROM staff WHERE staff_id = ? LIMIT 1");
    if ($photo_stmt) {
        $photo_stmt->bind_param("s", $staff_id);
        $photo_stmt->execute();
        $photo_row = $photo_stmt->get_result()->fetch_assoc();
        $staff_branch_name = $photo_row['branch'] ?? '';
        $p = $photo_row['photo'] ?? '';
        $staff_photo = $p;
        $staff_job_title = $photo_row['job_title'] ?? '';
        $staff_department = $photo_row['department'] ?? '';
        $staff_photo_ok = strlen($p) > 500 && str_starts_with($p, 'data:image');
        
        if (!$staff_photo_ok && !empty($p)) {
            $staff_photo_error = "Photo exists but format is invalid (length: " . strlen($p) . "). Please re-upload.";
        } elseif (empty($p)) {
            $staff_photo_error = "No profile photo uploaded. Please ask admin to upload your photo.";
        }
        $photo_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Dashboard — Attendance System</title>
  <link rel="stylesheet" href="/asset/css/style.css?v=<?php echo $style_version; ?>">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    body.dashboard-page { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); overflow-x: hidden; }
    .dashboard-page .content { width: 100%; max-width: 100%; box-sizing: border-box; padding: 20px; }

    .dashboard-header {
        background: linear-gradient(135deg, #1e293b, #334155);
        color: white; padding: clamp(20px, 4vw, 40px); border-radius: clamp(16px, 3vw, 24px);
        margin-bottom: clamp(16px, 3vw, 30px);
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden;
    }
    .dashboard-header h2 { font-size: clamp(1.25rem, 4vw, 2rem); font-weight: 800; margin: 0 0 8px; line-height: 1.2; }
    .dashboard-header p { margin: 0; font-size: clamp(0.85rem, 2.5vw, 1rem); opacity: 0.9; }

    .clocking-card {
        background: white; padding: clamp(16px, 4vw, 30px); border-radius: clamp(16px, 3vw, 24px);
        margin-bottom: clamp(16px, 3vw, 30px);
        border: 1px solid var(--border); box-shadow: var(--shadow);
    }
    .clocking-card h3 { font-size: clamp(1rem, 3vw, 1.25rem); margin-bottom: 8px; }

    #camera-container {
        width: min(280px, 70vw); margin: 0 auto 20px;
        border-radius: 50%; overflow: hidden; background: #000;
        aspect-ratio: 1 / 1; position: relative; border: 6px solid #e2e8f0;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        transition: border-color 0.3s ease;
    }
    #video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
    #canvas { display: none; }

    .scanning-overlay {
        position: absolute; inset: 0; border-radius: 50%;
        box-shadow: inset 0 0 0 10px rgba(59, 130, 246, 0.5);
        animation: pulse 1.5s infinite; display: none; z-index: 10;
    }
    @keyframes pulse { 0% { transform: scale(0.95); opacity: 0.5; } 50% { transform: scale(1); opacity: 1; } 100% { transform: scale(0.95); opacity: 0.5; } }

    #clockControls { display: flex; justify-content: center; width: 100%; }

    .clock-btn {
        background: var(--primary); color: white; border: none;
        padding: clamp(14px, 3vw, 18px) clamp(24px, 6vw, 50px);
        font-size: clamp(0.95rem, 2.8vw, 1.125rem); font-weight: 700;
        border-radius: 16px; cursor: pointer; transition: all 0.3s var(--ease);
        box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        width: 100%; max-width: 360px;
    }
    .clock-btn.out { background: #ef4444; box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3); }
    .clock-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; box-shadow: none; }

    .geo-register-btn {
        background: #0f766e; color: white; border: none; padding: 12px 20px;
        border-radius: 12px; font-weight: 600; font-size: 14px; cursor: pointer;
        margin-top: 10px; width: 100%; max-width: 360px;
    }
    .geo-register-btn:hover { background: #0d9488; }
    .gps-coords-box {
        background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px;
        padding: 12px; margin: 12px 0; font-size: 13px; text-align: left;
        color: #0c4a6e; word-break: break-all;
    }

    .status-line { margin-top: 8px; color: var(--text-muted); font-size: clamp(0.8rem, 2.5vw, 0.875rem); word-break: break-word; }
    #apiResult { margin-top: 15px; font-weight: 600; font-size: clamp(0.95rem, 2.8vw, 1.125rem); word-break: break-word; }
    .error-message { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 12px; margin: 10px 0; font-size: 14px; }

    /* ── Enhanced Search Box ── */
    .search-section { position: relative; width: 100%; }
    .search-section .search-icon {
        position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
        font-size: 20px; pointer-events: none; z-index: 2;
    }
    .search-input {
        width: 100%; padding: 16px 20px 16px 52px; box-sizing: border-box;
        background: white;
        border: 2.5px solid #4f46e5;
        border-radius: 18px; font-size: 16px; font-weight: 600;
        margin-bottom: 24px;
        box-shadow: 0 4px 20px rgba(79,70,229,0.18), 0 1px 4px rgba(0,0,0,0.07);
        transition: border-color 0.2s, box-shadow 0.2s;
        outline: none;
        color: #1e293b;
    }
    .search-input::placeholder { color: #94a3b8; font-weight: 500; }
    .search-input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99,102,241,0.18), 0 4px 20px rgba(79,70,229,0.18);
    }
    .search-hint {
        font-size: 12px; color: #6366f1; font-weight: 600; margin: -18px 0 18px 6px;
        opacity: 0.85;
    }

    /* ── Branch Scorecard ── */
    .scorecard-section { margin-bottom: 32px; }
    .scorecard-title {
        font-size: 18px; font-weight: 800; color: var(--text);
        margin-bottom: 16px; display: flex; align-items: center; gap: 10px;
    }
    .scorecard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
        max-height: 520px;
        overflow-y: auto;
        padding-right: 4px;
    }
    .scorecard-grid::-webkit-scrollbar { width: 6px; }
    .scorecard-grid::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 99px; }
    .branch-score-card {
        background: white;
        border: 1px solid #e0e7ff;
        border-radius: 18px;
        padding: 18px 20px;
        box-shadow: 0 4px 16px rgba(79,70,229,0.08);
        position: relative;
        overflow: hidden;
        transition: transform 0.18s, box-shadow 0.18s;
    }
    .branch-score-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 28px rgba(79,70,229,0.16);
    }
    .branch-score-card::before {
        content: '';
        position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(90deg, #4f46e5, #7c3aed);
        border-radius: 18px 18px 0 0;
    }
    .branch-score-card.no-branch::before {
        background: linear-gradient(90deg, #94a3b8, #64748b);
    }
    .branch-score-name {
        font-weight: 800; font-size: 14px; color: #1e293b;
        margin-bottom: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .branch-score-stats {
        display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
    }
    .branch-stat {
        background: #f8fafc; border-radius: 10px; padding: 10px 12px;
        text-align: center;
    }
    .branch-stat-label {
        font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.8px; color: #64748b; margin-bottom: 4px;
    }
    .branch-stat-value {
        font-size: 22px; font-weight: 800; color: #1e293b;
    }
    .branch-stat-value.in-val  { color: #10b981; }
    .branch-stat-value.out-val { color: #3b82f6; }
    .branch-stat-value.wk-val  { color: #8b5cf6; }
    .branch-stat-sub {
        font-size: 10px; color: #94a3b8; font-weight: 600;
        margin-top: 2px;
    }

    /* ── Broadcast Panel ── */
    .broadcast-panel {
        background: linear-gradient(135deg, #fdf4ff 0%, #ede9fe 100%);
        border: 1.5px solid #c4b5fd;
        border-radius: 24px;
        padding: 24px 28px;
        margin-bottom: 28px;
        box-shadow: 0 8px 24px rgba(139,92,246,0.1);
    }
    .broadcast-panel h3 {
        font-size: 17px; font-weight: 800; color: #5b21b6;
        margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
    }
    .broadcast-row {
        display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;
    }
    .broadcast-target {
        flex: 0 0 220px; min-width: 160px;
        padding: 12px 16px; border: 1.5px solid #c4b5fd;
        border-radius: 12px; font-size: 14px; font-weight: 600;
        background: white; color: #5b21b6;
        outline: none; cursor: pointer;
    }
    .broadcast-msg {
        flex: 1; min-width: 200px;
        padding: 12px 16px; border: 1.5px solid #c4b5fd;
        border-radius: 12px; font-size: 14px; font-weight: 600;
        background: white; outline: none;
        resize: none;
    }
    .broadcast-msg:focus, .broadcast-target:focus {
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124,58,237,0.12);
    }
    .broadcast-send-btn {
        padding: 12px 24px; border: none; border-radius: 12px;
        background: linear-gradient(135deg, #7c3aed, #4f46e5);
        color: white; font-weight: 800; font-size: 14px;
        cursor: pointer; white-space: nowrap;
        box-shadow: 0 4px 14px rgba(124,58,237,0.35);
        transition: transform 0.15s, box-shadow 0.15s;
    }
    .broadcast-send-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(124,58,237,0.4);
    }
    .broadcast-send-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
    .broadcast-status { font-size: 13px; font-weight: 600; margin-top: 10px; min-height: 20px; }

    /* ── Collapsible Attendance Log Panel ── */
    .att-log-panel {
        background: white;
        border-radius: 24px;
        border: 1px solid #e0e7ff;
        box-shadow: 0 4px 24px rgba(79,70,229,0.09);
        overflow: hidden;
        margin-bottom: 28px;
    }
    .att-log-toggle {
        width: 100%; background: none; border: none;
        padding: 20px 24px;
        display: flex; align-items: center; gap: 14px;
        cursor: pointer;
        text-align: left;
        transition: background 0.18s;
    }
    .att-log-toggle:hover { background: #f5f3ff; }
    .att-log-toggle-icon {
        width: 44px; height: 44px; border-radius: 14px; flex-shrink: 0;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: white; font-size: 22px;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 6px 16px rgba(79,70,229,0.28);
    }
    .att-log-toggle-text { flex: 1; }
    .att-log-toggle-title {
        font-size: 16px; font-weight: 800; color: #1e293b; margin-bottom: 2px;
    }
    .att-log-toggle-sub {
        font-size: 12px; color: #64748b; font-weight: 600;
    }
    .att-log-toggle-badges { display: flex; gap: 8px; align-items: center; }
    .att-log-badge {
        padding: 4px 12px; border-radius: 99px;
        font-size: 11px; font-weight: 700; letter-spacing: 0.4px;
    }
    .att-log-badge.green  { background: #dcfce7; color: #166534; }
    .att-log-badge.blue   { background: #dbeafe; color: #1e40af; }
    .att-log-badge.red    { background: #fee2e2; color: #991b1b; }
    .att-log-chevron {
        width: 32px; height: 32px; border-radius: 10px;
        background: #eef2ff; color: #4f46e5;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px; flex-shrink: 0;
        transition: transform 0.3s ease;
    }
    .att-log-chevron.open { transform: rotate(180deg); }
    .att-log-body {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.45s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .att-log-body.open {
        max-height: 2000px;
    }
    .att-log-body-inner { padding: 0 20px 20px; }

    /* ── Admin Notification Toast ── */
    .admin-notif-toast {
        position: fixed; bottom: 28px; right: 28px; z-index: 99998;
        background: linear-gradient(135deg, #7c3aed, #4f46e5);
        color: white; border-radius: 18px;
        padding: 18px 22px; max-width: 380px;
        box-shadow: 0 16px 40px rgba(79,70,229,0.35);
        display: flex; flex-direction: column; gap: 6px;
        transform: translateX(120%); opacity: 0;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease;
        pointer-events: none;
    }
    .admin-notif-toast.show {
        transform: translateX(0); opacity: 1; pointer-events: auto;
    }
    .admin-notif-toast-header {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 1px; opacity: 0.8;
    }
    .admin-notif-toast-msg { font-size: 15px; font-weight: 700; line-height: 1.4; }
    .admin-notif-toast-meta { font-size: 11px; opacity: 0.75; }
    .admin-notif-close {
        position: absolute; top: 10px; right: 14px;
        background: rgba(255,255,255,0.2); border: none;
        color: white; border-radius: 50%; width: 26px; height: 26px;
        cursor: pointer; font-size: 14px; display: flex;
        align-items: center; justify-content: center;
    }

    .recent-table {
        background: white; border-radius: clamp(16px, 3vw, 24px); padding: clamp(8px, 2vw, 10px);
        border: 1px solid var(--border); box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    .recent-table h3 { margin: clamp(12px, 3vw, 20px); font-size: clamp(1rem, 3vw, 1.125rem); }

    .table-scroll {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table-scroll table { 
        width: 100%; 
        min-width: 500px; 
        border-collapse: collapse; 
    }
    
    /* Mobile responsive table */
    @media (max-width: 768px) {
        .table-scroll table {
            min-width: 100%;
        }
        .table-scroll th, 
        .table-scroll td {
            padding: 10px 8px;
            font-size: 12px;
        }
        .dashboard-page .content {
            padding: 12px;
        }
    }
    
    .dashboard-page .recent-table th {
        text-align: left; padding: clamp(10px, 2.5vw, 18px) clamp(12px, 2.5vw, 20px);
        font-size: clamp(0.75rem, 2.2vw, 0.875rem);
        white-space: nowrap;
    }
    .dashboard-page .recent-table td {
        padding: clamp(10px, 2.5vw, 16px) clamp(12px, 2.5vw, 20px);
        font-size: clamp(0.8rem, 2.4vw, 0.9375rem);
    }
    .staff-thumb { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; margin-right: 8px; vertical-align: middle; }

    .dashboard-page .footer { margin-top: 24px; font-size: clamp(0.75rem, 2vw, 0.875rem); text-align: center; }

    @media (max-width: 600px) {
        .staff-thumb { width: 32px; height: 32px; }
    }

    @media (max-width: 480px) {
        #camera-container { width: min(240px, 80vw); border-width: 4px; }
        .clock-btn { padding: 12px 20px; font-size: 14px; }
        .dashboard-widgets { gap: 12px; }
        .dashboard-links-container {
            flex-direction: column !important;
            gap: 8px !important;
        }
    }

    /* Floating top notification banner */
    .top-notification {
        position: fixed;
        top: -120px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 99999;
        padding: 16px 24px;
        border-radius: 16px;
        font-weight: 700;
        font-size: 15px;
        box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        transition: top 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
        max-width: 90%;
        width: 480px;
        box-sizing: border-box;
        opacity: 0;
        pointer-events: none;
    }
    .top-notification.show {
        top: 24px;
        opacity: 1;
        pointer-events: auto;
    }
    .top-notification.success {
        background: #f0fdf4;
        color: #166534;
        border: 2px solid #22c55e;
        box-shadow: 0 10px 25px rgba(34, 197, 94, 0.2);
    }
    .top-notification.error {
        background: #fef2f2;
        color: #991b1b;
        border: 2px solid #ef4444;
        box-shadow: 0 10px 25px rgba(239, 68, 68, 0.2);
    }
    
    .clickable-card {
        cursor: pointer;
        transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .clickable-card:hover {
        transform: translateY(-4px) scale(1.02);
        box-shadow: 0 15px 30px -5px rgba(0,0,0,0.2) !important;
    }
    
    .dashboard-modal-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 100000;
        display: none;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .dashboard-modal-overlay.show {
        display: flex;
        opacity: 1;
    }
    .dashboard-modal {
        background: var(--surface);
        width: 90%;
        max-width: 600px;
        border-radius: 24px;
        box-shadow: var(--shadow-xl);
        overflow: hidden;
        transform: translateY(20px);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: flex;
        flex-direction: column;
        max-height: 85vh;
    }
    .dashboard-modal-overlay.show .dashboard-modal {
        transform: translateY(0);
    }
    .dashboard-modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, var(--surface), var(--surface-alt));
    }
    .dashboard-modal-title {
        font-size: 18px;
        font-weight: 800;
        color: var(--text);
    }
    .dashboard-modal-close {
        background: var(--surface-alt);
        border: 1px solid var(--border);
        width: 36px; height: 36px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        color: var(--text-muted);
        transition: all 0.2s;
    }
    .dashboard-modal-close:hover {
        background: #ef4444;
        color: white;
        border-color: #ef4444;
        transform: rotate(90deg);
    }
    .dashboard-modal-body {
        padding: 0;
        overflow-y: auto;
    }
    .dashboard-modal-list {
        list-style: none;
        margin: 0; padding: 0;
    }
    .dashboard-modal-list li {
        padding: 16px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: background 0.2s;
    }
    .dashboard-modal-list li:hover {
        background: var(--surface-alt);
    }
    .dashboard-modal-list li:last-child {
        border-bottom: none;
    }
    
    /* ── Animated Scorecards CSS ── */
    .premium-scorecard {
        position: relative;
        overflow: hidden;
        color: white;
        padding: 24px;
        border-radius: 24px;
        box-shadow: 0 15px 35px -5px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
        justify-content: center;
        z-index: 1;
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease;
    }
    .premium-scorecard:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 20px 40px -5px rgba(0,0,0,0.3);
    }
    .premium-scorecard::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(120deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 100%);
        z-index: -1;
    }
    .premium-scorecard .animated-graph {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 60px;
        z-index: -1;
        opacity: 0.3;
        background-image: url('data:image/svg+xml;utf8,<svg viewBox="0 0 100 20" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0,20 L0,10 C10,15 20,5 30,10 C40,15 50,2 60,10 C70,18 80,8 90,12 C95,14 100,10 100,10 L100,20 Z" fill="white"/></svg>');
        background-size: 200% 100%;
        animation: wave-animation 4s linear infinite;
    }
    @keyframes wave-animation {
        0% { background-position: 100% 0; }
        100% { background-position: 0 0; }
    }
    .premium-scorecard.purple { background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); }
    .premium-scorecard.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    .premium-scorecard.orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    .premium-scorecard.pink { background: linear-gradient(135deg, #ec4899 0%, #be185d 100%); }
    .premium-scorecard.blue { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
    .premium-scorecard.red { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); }
    
    /* ── Accordion CSS ── */
    .accordion-btn {
        background: #f1f5f9;
        border: none;
        width: 100%;
        text-align: left;
        padding: 16px 20px;
        border-radius: 16px;
        font-weight: 700;
        font-size: 15px;
        color: #334155;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.3s;
        margin-top: 16px;
    }
    .accordion-btn:hover { background: #e2e8f0; }
    .accordion-btn .icon {
        transition: transform 0.3s;
        font-size: 20px;
    }
    .accordion-btn.active .icon { transform: rotate(180deg); }
    .accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s ease-out;
        padding: 0;
    }
    .accordion-content.active {
        max-height: 1000px; /* arbitrary large value */
        margin-top: 16px;
    }
    
  </style>
</head>
<body class="app-page dashboard-page">
  <div id="topNotification" class="top-notification"></div>

  <?php include(__DIR__ . "/includes/sidebar.php"); ?>

  <div class="content">
<?php
    $hour = date('H');
    $greeting = "Good evening";
    if ($hour < 12) {
        $greeting = "Good morning";
    } elseif ($hour < 18) {
        $greeting = "Good afternoon";
    }
?>
    <div class="dashboard-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
            <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($display_name); ?> 👋</h2>
            <p>Facial verification active · Auto sign-out after 1 minute of inactivity</p>
        </div>
        <div style="position:relative; margin-top: 10px;">
            <button onclick="toggleInboxModal()" style="background:var(--surface); border:1px solid var(--border); border-radius:50%; width:48px; height:48px; display:flex; align-items:center; justify-content:center; cursor:pointer; position:relative; box-shadow:var(--shadow-sm); transition:all 0.2s;">
                <span style="font-size:22px;">🔔</span>
                <span id="inboxBadge" style="position:absolute; top:-4px; right:-4px; background:#ef4444; color:white; font-size:11px; font-weight:800; padding:2px 6px; border-radius:99px; display:none; border:2px solid var(--surface);">0</span>
            </button>
        </div>
    </div>

    <!-- Live Time & Date Widgets -->
    <div class="dashboard-widgets" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 30px;">
        <div class="widget-card time-widget" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white; padding: 24px 30px; border-radius: 24px; box-shadow: 0 15px 30px -5px rgba(79, 70, 229, 0.4); position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between;">
            <div style="position: absolute; top: -20px; right: -20px; width: 120px; height: 120px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            <div style="position: absolute; bottom: -40px; left: 10px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            
            <div style="position: relative; z-index: 2;">
                <div style="font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.85;" id="liveDateStr">Loading...</div>
                <div style="font-size: clamp(32px, 8vw, 42px); font-weight: 800; margin: 4px 0; font-variant-numeric: tabular-nums; display: flex; align-items: baseline; gap: 8px; letter-spacing: -1px;">
                    <span id="liveTime">00:00:00</span>
                    <span id="liveAmPm" style="font-size: 20px; font-weight: 600; opacity: 0.9;"></span>
                </div>
            </div>
            <div style="position: relative; z-index: 2; opacity: 0.9; font-size: 48px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));">
                ⏰
            </div>
        </div>
        
        <div class="widget-card info-widget" style="background: white; padding: 24px 30px; border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
            <div style="width: 64px; height: 64px; border-radius: 18px; background: var(--info-bg); color: var(--info); display: flex; align-items: center; justify-content: center; font-size: 32px; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5);">
                📅
            </div>
            <div>
                <div style="font-size: 13px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Today is</div>
                <div style="font-size: 24px; font-weight: 800; color: var(--text);" id="liveDayName">Loading...</div>
            </div>
        </div>
    </div>

    <?php if ($is_admin && !$staff_id): ?>
    <div style="background:#fff7ed;border:1px solid #fdba74;color:#9a3412;padding:16px 20px;border-radius:16px;margin-bottom:24px;font-size:15px;line-height:1.5;">
        <strong>Admin account:</strong> Face clock-in only works for <strong>staff</strong> logins.
        Log out and sign in with your <strong>Staff ID</strong> and password (not your admin username).
    </div>
    <?php endif; ?>

    <?php if ($staff_id): ?>
    <!-- Display photo error if any -->
    <?php if ($staff_photo_error): ?>
    <div class="error-message">
        <strong>⚠️ Face Verification Issue:</strong> <?php echo htmlspecialchars($staff_photo_error); ?>
    </div>
    <?php endif; ?>
    
    <div class="clocking-card">
        <h3 style="text-align:center;">📸 Face Verification & Clock In/Out</h3>
        <p style="text-align:center;color:var(--text-muted);margin-bottom:20px;">Look at the camera and click the button below</p>
        <div id="camera-container">
            <video id="video" autoplay playsinline></video>
            <div id="scanningOverlay" class="scanning-overlay"></div>
            <canvas id="canvas" width="640" height="480"></canvas>
        </div>
        <div id="apiResult" style="text-align:center;"></div>

        <?php
            // Auto-close missed clock-out from previous day(s)
            $missed_stmt = $conn->prepare("SELECT id, clock_in FROM `attendance` WHERE staff_id = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
            if ($missed_stmt) {
                $missed_stmt->bind_param("s", $staff_id);
                $missed_stmt->execute();
                $missed_res = $missed_stmt->get_result();
                if ($missed_res && $missed_res->num_rows > 0) {
                    $m = $missed_res->fetch_assoc();
                    $clock_in_date = date("Y-m-d", strtotime($m['clock_in']));
                    $today_date    = date("Y-m-d");
                    if ($clock_in_date < $today_date) {
                        $midnight   = date("Y-m-d 00:00:00", strtotime($clock_in_date . " +1 day"));
                        $upd_missed = $conn->prepare("UPDATE `attendance` SET clock_out = ?, status = 'missed_out', total_hours = 0 WHERE id = ? AND clock_out IS NULL");
                        if ($upd_missed) { $upd_missed->bind_param("si", $midnight, $m['id']); $upd_missed->execute(); $upd_missed->close(); }
                    }
                }
                $missed_stmt->close();
            }

            // ── Multi-session logic (up to 3 pairs per day) ────────────
            $MAX_SESSIONS   = 3;
            $sessions_today = [];
            $stmt_sessions  = $conn->prepare("SELECT id, clock_in, clock_out FROM `attendance` WHERE staff_id = ? AND DATE(clock_in) = CURDATE() ORDER BY id ASC");
            if ($stmt_sessions) {
                $stmt_sessions->bind_param("s", $staff_id);
                $stmt_sessions->execute();
                $res_sessions = $stmt_sessions->get_result();
                while ($sr = $res_sessions->fetch_assoc()) {
                    $sessions_today[] = $sr;
                }
                $stmt_sessions->close();
            }

            $total_sessions    = count($sessions_today);
            $last_session      = $total_sessions > 0 ? $sessions_today[$total_sessions - 1] : null;
            $currently_in      = $last_session && $last_session['clock_out'] === null;
            $all_sessions_used = $total_sessions >= $MAX_SESSIONS && !$currently_in;
            $can_clock_in      = !$currently_in && $total_sessions < $MAX_SESSIONS;
            $can_clock_out     = $currently_in;

            // 6PM cutoff
            $past_6pm = (int)date('G') >= 18;
            $session_labels = ['Morning', 'Midday', 'Afternoon'];
            $next_session_label = $session_labels[min($total_sessions, 2)];
        ?>

        <div id="clockControls">
            <?php if ($past_6pm): ?>
                <button id="clockBtn" class="clock-btn" disabled data-prevent-enable="true"
                    style="background:#64748b;box-shadow:none;">⏰ Clock-in/out disabled after 6:00 PM</button>
            <?php elseif ($all_sessions_used): ?>
                <button id="clockBtn" class="clock-btn" disabled data-prevent-enable="true"
                    style="background:#64748b;box-shadow:none;">✅ All <?php echo $MAX_SESSIONS; ?> sessions complete for today</button>
            <?php elseif ($can_clock_out): ?>
                <button id="clockBtn" class="clock-btn out" disabled onclick="processClocking('clock_out')">Verify &amp; Clock Out</button>
            <?php elseif ($can_clock_in): ?>
                <button id="clockBtn" class="clock-btn" disabled onclick="processClocking('clock_in')">Verify &amp; Clock In <?php if($total_sessions > 0): ?>(<?php echo $next_session_label; ?>)<?php endif; ?></button>
            <?php else: ?>
                <button id="clockBtn" class="clock-btn" disabled data-prevent-enable="true"
                    style="background:#64748b;box-shadow:none;">Already Clocked Out Today</button>
            <?php endif; ?>
        </div>
        <!-- Sessions counter -->
        <p style="text-align:center;font-size:13px;color:var(--text-muted);margin-top:10px;font-weight:600;">
            📊 Sessions today: <strong style="color:var(--primary);"><?php echo $total_sessions; ?> / <?php echo $MAX_SESSIONS; ?></strong>
            <?php if($past_6pm): ?>&nbsp;· <span style="color:#ef4444;font-weight:700;">⏰ After 6 PM</span><?php endif; ?>
        </p>
        
        <p id="faceStatus" class="status-line">🔄 Loading face recognition...</p>
        <div id="gpsCoordsBox" class="gps-coords-box" style="display:none;">
            <strong>Your GPS location:</strong><br>
            <span id="liveGpsText">Waiting…</span>
        </div>
        <p id="geoStatus" class="status-line">📍 Detecting location...</p>
        <button type="button" id="registerLocBtn" class="geo-register-btn" onclick="registerMyClockLocation()">
            📍 Register my clock location
        </button>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px;text-align:center;">
            Use this to set your exact clock-in location if GPS is inaccurate
        </p>
    </div>

    <!-- ── Field Work Comment Box (Staff Only) ── -->
    <div class="clocking-card" id="fieldWorkCard" style="margin-top:0;border-top:2px solid #fbbf24;background:linear-gradient(135deg,#fffbeb,#fef3c7);">
        <h3 style="color:#92400e;display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <span style="background:#f59e0b;color:white;width:36px;height:36px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:18px;">🏃</span>
            Going for Field Work?
        </h3>
        <p style="color:#78350f;font-size:13px;margin-bottom:14px;font-weight:600;">
            If you are going to the field and may not return to clock out before day-end, leave a note here for your manager.
        </p>
        <textarea id="fieldWorkMsg" rows="3" maxlength="1000"
            style="width:100%;padding:12px 16px;border:1.5px solid #fbbf24;border-radius:14px;font-size:14px;font-weight:600;font-family:inherit;background:white;outline:none;resize:vertical;box-sizing:border-box;color:#1e293b;"
            placeholder="e.g. I am heading to ABC site and will not return before 6 PM. My return is expected on [date]."></textarea>
        <button onclick="submitFieldWorkComment()" id="fieldWorkBtn"
            style="margin-top:12px;padding:12px 24px;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border:none;border-radius:14px;font-weight:800;font-size:14px;cursor:pointer;width:100%;box-shadow:0 6px 16px rgba(245,158,11,0.35);transition:all 0.2s;">
            📤 Submit Field Work Note
        </button>
        <div id="fieldWorkStatus" style="margin-top:10px;font-size:13px;font-weight:700;min-height:20px;text-align:center;"></div>
    </div>

    <div class="clocking-card">
        <div style="display:flex;align-items:center;gap:14px;justify-content:space-between;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:14px;">
                <?php if ($staff_photo_ok): ?>
                    <img src="<?php echo htmlspecialchars($staff_photo); ?>" alt="Profile" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid rgba(79,70,229,.35);">
                <?php else: ?>
                    <div style="width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#e2e8f0;font-weight:800;">?</div>
                <?php endif; ?>
                <div>
                    <div style="font-weight:800;font-size:16px;"><?php echo htmlspecialchars($display_name); ?></div>
                    <div style="color:var(--text-muted);font-weight:600;font-size:13px;">
                        <?php echo htmlspecialchars($staff_id); ?>
                        <?php if ($staff_job_title): ?> · <?php echo htmlspecialchars($staff_job_title); ?><?php endif; ?>
                        <?php if ($staff_department): ?> · <?php echo htmlspecialchars($staff_department); ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="dashboard-links-container" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;">
                <a href="my_attendance.php" style="padding:10px 14px;border-radius:14px;background:var(--surface-alt);border:1px solid var(--border);font-weight:700;text-decoration:none;flex:1;min-width:120px;text-align:center;">My Attendance</a>
                <a href="my_report.php" style="padding:10px 14px;border-radius:14px;background:var(--surface-alt);border:1px solid var(--border);font-weight:700;text-decoration:none;flex:1;min-width:120px;text-align:center;">My Report</a>
                <a href="my_profile.php" style="padding:10px 14px;border-radius:14px;background:linear-gradient(135deg,var(--primary),var(--primary-light));border:1px solid transparent;color:#fff;font-weight:800;text-decoration:none;flex:1;min-width:120px;text-align:center;">My Profile</a>
            </div>
        </div>
    </div>
    
    <div class="recent-table" style="margin-top: 30px;">
        <h3 style="display:flex;align-items:center;gap:10px;">
            <div style="background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; width: 36px; height: 36px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);">🕒</div>
            Your Recent Attendance
        </h3>
        
        <?php
        $today_records = [];
        $history_records = [];
        
        $stmt = $conn->prepare("SELECT clock_in, clock_out, status FROM attendance WHERE staff_id = ? ORDER BY clock_in DESC LIMIT 15");
        if ($stmt) {
            $stmt->bind_param("s", $staff_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $clock_date = date("Y-m-d", strtotime($r['clock_in']));
                if ($clock_date === $today_date) {
                    $today_records[] = $r;
                } else {
                    $history_records[] = $r;
                }
            }
            $stmt->close();
        }
        ?>
        
        <!-- Today's Attendance Display -->
        <div style="background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #e2e8f0; box-shadow: inset 0 2px 4px rgba(255,255,255,0.7);">
            <h4 style="margin: 0 0 12px 0; font-size: 15px; color: #475569; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Today's Record</h4>
            <?php if (empty($today_records)): ?>
                <div style="text-align: center; color: #94a3b8; font-weight: 600; padding: 10px 0;">No clock-in record for today yet.</div>
            <?php else: ?>
                <?php foreach ($today_records as $tr): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 16px 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <div style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Clock In</div>
                            <div style="font-size: 18px; font-weight: 800; color: #10b981;"><?php echo date("h:i A", strtotime($tr['clock_in'])); ?></div>
                        </div>
                        <div style="width: 1px; height: 30px; background: #e2e8f0;"></div>
                        <div>
                            <div style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Clock Out</div>
                            <div style="font-size: 18px; font-weight: 800; color: <?php echo $tr['clock_out'] ? '#ef4444' : '#94a3b8'; ?>;">
                                <?php echo $tr['clock_out'] ? date("h:i A", strtotime($tr['clock_out'])) : 'Still Working'; ?>
                            </div>
                        </div>
                        <div style="width: 1px; height: 30px; background: #e2e8f0;"></div>
                        <div>
                            <?php
                            $badgeClass = 'badge-warning';
                            $label = strtoupper((string)($tr['status'] ?? ''));
                            if ($tr['status'] === 'in') { $badgeClass = 'badge-success'; $label = 'WORKING'; }
                            elseif ($tr['status'] === 'out') { $badgeClass = 'badge-info'; $label = 'COMPLETED'; }
                            elseif ($tr['status'] === 'missed_out') { $badgeClass = 'badge-danger'; $label = 'MISSED OUT'; }
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>" style="padding: 8px 14px; font-size: 12px; font-weight: 800;"><?php echo $label; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Accordion for Historical Attendance -->
        <?php if (!empty($history_records)): ?>
        <button class="accordion-btn" onclick="toggleHistoryAccordion(this)">
            <span>📅 View Previous Days</span>
            <span class="icon">▾</span>
        </button>
        <div class="accordion-content">
            <div class="table-scroll" style="border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
                <table id="staffAttendanceTable" class="responsive-table">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_records as $r): ?>
                            <?php
                                $badgeClass = 'badge-warning';
                                $label = strtoupper((string)($r['status'] ?? ''));
                                if ($r['status'] === 'in') { $badgeClass = 'badge-success'; $label = 'IN'; }
                                elseif ($r['status'] === 'out') { $badgeClass = 'badge-info'; $label = 'OUT'; }
                                elseif ($r['status'] === 'missed_out') { $badgeClass = 'badge-danger'; $label = 'missed(out)'; }
                                $stat = "<span class='badge {$badgeClass}'>{$label}</span>";
                                $out = $r['clock_out'] ? date("h:i A", strtotime($r['clock_out'])) : '—';
                            ?>
                            <tr>
                                <td data-label='Date' style="font-weight: 600;"><?php echo date("M d, Y", strtotime($r['clock_in'])); ?></td>
                                <td data-label='Clock In' style="color:#10b981; font-weight: 700;"><?php echo date("h:i A", strtotime($r['clock_in'])); ?></td>
                                <td data-label='Clock Out' style="color:#ef4444; font-weight: 700;"><?php echo $out; ?></td>
                                <td data-label='Status'><?php echo $stat; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            function toggleHistoryAccordion(btn) {
                btn.classList.toggle('active');
                const content = btn.nextElementSibling;
                if (content.style.maxHeight && content.style.maxHeight !== '0px') {
                    content.style.maxHeight = '0px';
                    content.classList.remove('active');
                } else {
                    content.style.maxHeight = content.scrollHeight + 'px';
                    content.classList.add('active');
                }
            }
        </script>
        <?php endif; ?>
    </div>
    
    <?php
    $stmt_th = $conn->prepare("SELECT SUM(total_hours) as th FROM attendance WHERE staff_id = ? AND YEARWEEK(clock_in, 1) = YEARWEEK(CURDATE(), 1) AND WEEKDAY(clock_in) <= 4");
    if ($stmt_th) {
        $stmt_th->bind_param("s", $staff_id);
        $stmt_th->execute();
        $th = $stmt_th->get_result()->fetch_assoc()['th'] ?? 0;
        $stmt_th->close();
    }
    
    $stmt_today = $conn->prepare("SELECT SUM(total_hours) as th FROM attendance WHERE staff_id = ? AND DATE(clock_in) = CURDATE()");
    if ($stmt_today) {
        $stmt_today->bind_param("s", $staff_id);
        $stmt_today->execute();
        $th_today = $stmt_today->get_result()->fetch_assoc()['th'] ?? 0;
        $stmt_today->close();
    }
    
    $stmt_yest = $conn->prepare("SELECT SUM(total_hours) as th FROM attendance WHERE staff_id = ? AND DATE(clock_in) = CURDATE() - INTERVAL 1 DAY");
    if ($stmt_yest) {
        $stmt_yest->bind_param("s", $staff_id);
        $stmt_yest->execute();
        $th_yest = $stmt_yest->get_result()->fetch_assoc()['th'] ?? 0;
        $stmt_yest->close();
    }
    
    $stmt_month_th = $conn->prepare("SELECT SUM(total_hours) as th FROM attendance WHERE staff_id = ? AND MONTH(clock_in) = MONTH(CURDATE()) AND YEAR(clock_in) = YEAR(CURDATE())");
    $th_month = 0;
    if ($stmt_month_th) {
        $stmt_month_th->bind_param("s", $staff_id);
        $stmt_month_th->execute();
        $th_month = $stmt_month_th->get_result()->fetch_assoc()['th'] ?? 0;
        $stmt_month_th->close();
    }
    
    $stmt_days = $conn->prepare("SELECT COUNT(DISTINCT DATE(clock_in)) as days FROM attendance WHERE staff_id = ? AND MONTH(clock_in) = MONTH(CURDATE()) AND YEAR(clock_in) = YEAR(CURDATE())");
    $days_present = 0;
    if ($stmt_days) {
        $stmt_days->bind_param("s", $staff_id);
        $stmt_days->execute();
        $days_present = $stmt_days->get_result()->fetch_assoc()['days'] ?? 0;
        $stmt_days->close();
    }
    
    $stmt_missed = $conn->prepare("SELECT COUNT(*) as missed FROM attendance WHERE staff_id = ? AND status = 'missed_out' AND MONTH(clock_in) = MONTH(CURDATE()) AND YEAR(clock_in) = YEAR(CURDATE())");
    $missed_count = 0;
    if ($stmt_missed) {
        $stmt_missed->bind_param("s", $staff_id);
        $stmt_missed->execute();
        $missed_count = $stmt_missed->get_result()->fetch_assoc()['missed'] ?? 0;
        $stmt_missed->close();
    }
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin-top: 24px;">
        <div class="premium-scorecard green">
            <div class="animated-graph"></div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px; border-radius: 8px;">⏳</span> Total Time This Week
            </div>
            <div style="font-size: 28px; font-weight: 800;" class="animate-hours" data-target="<?php echo (float)$th; ?>">0min</div>
        </div>
        
        <div class="premium-scorecard orange">
            <div class="animated-graph" style="animation-delay: -1s;"></div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; display: flex; align-items: center; gap: 8px;" id="timeTrackedLabel">
                    <span style="background: rgba(255,255,255,0.2); padding: 4px; border-radius: 8px;">☀️</span> Today's Hours
                </div>
                <button onclick="toggleDayTracked()" style="background: rgba(255,255,255,0.25); border: 1px solid rgba(255,255,255,0.4); color: white; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s;">
                    <svg id="timeTrackedIcon" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>
                </button>
            </div>
            <div style="font-size: 28px; font-weight: 800;" id="timeTrackedValue" class="animate-hours" data-target="<?php echo (float)$th_today; ?>">0min</div>
        </div>
        
        <div class="premium-scorecard purple">
            <div class="animated-graph" style="animation-delay: -2s;"></div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px; border-radius: 8px;">🗓️</span> Days Present (Month)
            </div>
            <div style="font-size: 28px; font-weight: 800;" class="animate-number" data-target="<?php echo (int)$days_present; ?>">0</div>
        </div>
        
        <div class="premium-scorecard blue">
            <div class="animated-graph" style="animation-delay: -0.5s;"></div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px; border-radius: 8px;">⏱️</span> Total Hours (Month)
            </div>
            <div style="font-size: 28px; font-weight: 800;" class="animate-hours" data-target="<?php echo (float)$th_month; ?>">0min</div>
        </div>
        
        <div class="premium-scorecard <?php echo $missed_count > 0 ? 'red' : 'pink'; ?>">
            <div class="animated-graph" style="animation-delay: -3s;"></div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px; border-radius: 8px;">⚠️</span> Missed Clock-Outs
            </div>
            <div style="font-size: 28px; font-weight: 800;" class="animate-number" data-target="<?php echo (int)$missed_count; ?>">0</div>
        </div>
    </div>

    <?php elseif ($is_admin): ?>
    <?php
    // Admin Summary Stats
    $total_staff = 0;
    $present_today = 0;
    $absent_today = 0;

    $res_staff = $conn->query("SELECT COUNT(*) as c FROM staff");
    if ($res_staff) {
        $total_staff = $res_staff->fetch_assoc()['c'];
    }

    $res_present = $conn->query("SELECT COUNT(DISTINCT staff_id) as c FROM attendance WHERE DATE(clock_in) = CURDATE()");
    if ($res_present) {
        $present_today = $res_present->fetch_assoc()['c'];
    }

    $absent_today = max(0, $total_staff - $present_today);
    
    $res_th_today = $conn->query("SELECT SUM(total_hours) as th FROM attendance WHERE DATE(clock_in) = CURDATE()");
    $admin_th_today = 0;
    if ($res_th_today) {
        $admin_th_today = $res_th_today->fetch_assoc()['th'] ?? 0;
    }
    
    $res_missed_yest = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE DATE(clock_in) = CURDATE() - INTERVAL 1 DAY AND status = 'missed_out'");
    $missed_yest = 0;
    if ($res_missed_yest) {
        $missed_yest = $res_missed_yest->fetch_assoc()['c'] ?? 0;
    }

    // 7-Day Attendance Trend for Chart.js
    $chart_labels = [];
    $chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date("Y-m-d", strtotime("-$i days"));
        $chart_labels[] = date("D", strtotime($d));
        $res_chart = $conn->query("SELECT COUNT(DISTINCT staff_id) as c FROM attendance WHERE DATE(clock_in) = '$d'");
        $chart_data[] = $res_chart ? $res_chart->fetch_assoc()['c'] : 0;
    }

    // ── Branch Scorecard Data ───────────────────────────────────────
    // Get all unique branches from branches table + staff with no branch
    $branch_scores = [];

    // Daily clock-in and clock-out per branch (staff.branch used)
    $score_daily_sql = "
        SELECT
            COALESCE(NULLIF(TRIM(s.branch),''), '__NO_BRANCH__') AS branch_key,
            COUNT(DISTINCT CASE WHEN DATE(a.clock_in) = CURDATE() THEN a.staff_id END) AS daily_in,
            COUNT(DISTINCT CASE WHEN DATE(a.clock_in) = CURDATE() AND a.clock_out IS NOT NULL AND a.status != 'missed_out' THEN a.staff_id END) AS daily_out
        FROM staff s
        LEFT JOIN attendance a ON a.staff_id = s.staff_id
        GROUP BY branch_key
        ORDER BY branch_key ASC
    ";
    $score_weekly_sql = "
        SELECT
            COALESCE(NULLIF(TRIM(s.branch),''), '__NO_BRANCH__') AS branch_key,
            COUNT(DISTINCT CASE WHEN YEARWEEK(a.clock_in,1) = YEARWEEK(CURDATE(),1) THEN a.staff_id END) AS weekly_in,
            COUNT(DISTINCT CASE WHEN YEARWEEK(a.clock_in,1) = YEARWEEK(CURDATE(),1) AND a.clock_out IS NOT NULL AND a.status != 'missed_out' THEN a.staff_id END) AS weekly_out
        FROM staff s
        LEFT JOIN attendance a ON a.staff_id = s.staff_id
        GROUP BY branch_key
        ORDER BY branch_key ASC
    ";

    $daily_map  = [];
    $weekly_map = [];

    $rd = $conn->query($score_daily_sql);
    if ($rd) { while ($row = $rd->fetch_assoc()) { $daily_map[$row['branch_key']] = $row; } }

    $rw = $conn->query($score_weekly_sql);
    if ($rw) { while ($row = $rw->fetch_assoc()) { $weekly_map[$row['branch_key']] = $row; } }

    // Merge keys
    $all_branch_keys = array_unique(array_merge(array_keys($daily_map), array_keys($weekly_map)));
    sort($all_branch_keys);

    foreach ($all_branch_keys as $bk) {
        $branch_scores[] = [
            'key'        => $bk,
            'name'       => ($bk === '__NO_BRANCH__') ? '⚫ No Branch Assigned' : $bk,
            'daily_in'   => (int)($daily_map[$bk]['daily_in']  ?? 0),
            'daily_out'  => (int)($daily_map[$bk]['daily_out'] ?? 0),
            'weekly_in'  => (int)($weekly_map[$bk]['weekly_in']  ?? 0),
            'weekly_out' => (int)($weekly_map[$bk]['weekly_out'] ?? 0),
        ];
    }

    // ── Fetch branches for broadcast dropdown ──────────────────────
    $branch_list_res = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name ASC");
    $branch_list = [];
    if ($branch_list_res) {
        while ($bl = $branch_list_res->fetch_assoc()) {
            $branch_list[] = $bl['branch_name'];
        }
    }
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
        <div class="premium-scorecard blue clickable-card" onclick="openDashboardDetailsModal('total_staff')">
            <div class="animated-graph"></div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px; border-radius: 8px;">👥</span> Total Staff
            </div>
            <div style="font-size: 28px; font-weight: 800;" class="animate-number" data-target="<?php echo (int)$total_staff; ?>">0</div>
        </div>
        
        <div class="premium-scorecard green clickable-card" onclick="openDashboardDetailsModal('present_today')">
            <div class="animated-graph" style="animation-delay: -1s;"></div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px; border-radius: 8px;">✅</span> Present Today
            </div>
            <div style="font-size: 28px; font-weight: 800;" class="animate-number" data-target="<?php echo (int)$present_today; ?>">0</div>
        </div>
        
        <div class="premium-scorecard red clickable-card" onclick="openDashboardDetailsModal('absent_today')">
            <div class="animated-graph" style="animation-delay: -2s;"></div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px; border-radius: 8px;">❌</span> Absent Today
            </div>
            <div style="font-size: 28px; font-weight: 800;" class="animate-number" data-target="<?php echo (int)$absent_today; ?>">0</div>
        </div>
        
        <div class="premium-scorecard orange clickable-card" onclick="openDashboardDetailsModal('total_branches')">
            <div class="animated-graph" style="animation-delay: -0.5s;"></div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px; border-radius: 8px;">🏢</span> Total Branches
            </div>
            <div style="font-size: 28px; font-weight: 800;" class="animate-number" data-target="<?php echo count($branch_scores); ?>">0</div>
        </div>
        
        <div class="premium-scorecard purple">
            <div class="animated-graph" style="animation-delay: -1.5s;"></div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px; border-radius: 8px;">⏱️</span> Total Hours Today
            </div>
            <div style="font-size: 28px; font-weight: 800;" class="animate-hours" data-target="<?php echo (float)$admin_th_today; ?>">0min</div>
        </div>
        
        <div class="premium-scorecard <?php echo $missed_yest > 0 ? 'pink' : 'green'; ?>">
            <div class="animated-graph" style="animation-delay: -2.5s;"></div>
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px; border-radius: 8px;">⚠️</span> Missed Clock-Out (Yest.)
            </div>
            <div style="font-size: 28px; font-weight: 800;" class="animate-number" data-target="<?php echo (int)$missed_yest; ?>">0</div>
        </div>
    </div>

    <!-- Chart.js Visualization -->
    <div style="background: white; border-radius: 24px; padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm); border: 1px solid var(--border);">
        <h3 style="margin-bottom: 15px; font-size: 16px; color: var(--text);">📊 7-Day Attendance Trend</h3>
        <canvas id="attendanceChart" height="80"></canvas>
    </div>

    <!-- ── Branch Scorecard ── -->
    <div class="scorecard-section">
        <div class="scorecard-title">
            🏆 Branch Attendance Scorecard
            <span style="font-size:12px;font-weight:600;color:#6366f1;background:#eef2ff;padding:4px 10px;border-radius:99px;">
                <?php echo count($branch_scores); ?> branch<?php echo count($branch_scores) !== 1 ? 'es' : ''; ?>
            </span>
        </div>
        <div class="scorecard-grid" id="scorecardGrid">
        <?php foreach ($branch_scores as $bs): ?>
            <div class="branch-score-card <?php echo $bs['key'] === '__NO_BRANCH__' ? 'no-branch' : ''; ?>"
                 data-branch="<?php echo htmlspecialchars(strtoupper($bs['name'])); ?>">
                <div class="branch-score-name" title="<?php echo htmlspecialchars($bs['name']); ?>">
                    <?php echo htmlspecialchars($bs['name']); ?>
                </div>
                <div class="branch-score-stats">
                    <div class="branch-stat clickable-card" onclick="openDashboardDetailsModal('branch_today_in', '<?php echo addslashes($bs['key']); ?>')">
                        <div class="branch-stat-label">Today In</div>
                        <div class="branch-stat-value in-val"><?php echo $bs['daily_in']; ?></div>
                        <div class="branch-stat-sub">clocked in</div>
                    </div>
                    <div class="branch-stat clickable-card" onclick="openDashboardDetailsModal('branch_today_out', '<?php echo addslashes($bs['key']); ?>')">
                        <div class="branch-stat-label">Today Out</div>
                        <div class="branch-stat-value out-val"><?php echo $bs['daily_out']; ?></div>
                        <div class="branch-stat-sub">clocked out</div>
                    </div>
                    <div class="branch-stat clickable-card" style="grid-column:1;" onclick="openDashboardDetailsModal('branch_week_in', '<?php echo addslashes($bs['key']); ?>')">
                        <div class="branch-stat-label">Week In</div>
                        <div class="branch-stat-value wk-val"><?php echo $bs['weekly_in']; ?></div>
                        <div class="branch-stat-sub">this week</div>
                    </div>
                    <div class="branch-stat clickable-card" onclick="openDashboardDetailsModal('branch_week_out', '<?php echo addslashes($bs['key']); ?>')">
                        <div class="branch-stat-label">Week Out</div>
                        <div class="branch-stat-value" style="color:#f59e0b;"><?php echo $bs['weekly_out']; ?></div>
                        <div class="branch-stat-sub">this week</div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Admin Broadcast Message ── -->
    <div class="broadcast-panel">
        <h3>📢 Send Notification to Staff</h3>
        <div class="broadcast-row">
            <select id="broadcastTarget" class="broadcast-target">
                <option value="all">📣 Everyone (All Staff)</option>
                <?php foreach ($branch_list as $bl): ?>
                <option value="<?php echo htmlspecialchars($bl); ?>">
                    📍 <?php echo htmlspecialchars($bl); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <textarea id="broadcastMsg" class="broadcast-msg" rows="2"
                placeholder="Type your message here… it will pop up as a notification for the selected staff."></textarea>
            <button class="broadcast-send-btn" id="broadcastSendBtn" onclick="sendBroadcast()">
                🚀 Send Now
            </button>
        </div>
        <div id="broadcastStatus" class="broadcast-status"></div>
    </div>

    <!-- ── Field Work Messages Panel (Admin View) ── -->
    <?php
        // Fetch today's field work comments
        $fw_count = 0;
        $fw_rows  = [];
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS field_work_comments (id INT AUTO_INCREMENT PRIMARY KEY, staff_id VARCHAR(50) NOT NULL, full_name VARCHAR(200), branch VARCHAR(100), comment TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, is_read TINYINT(1) DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $fw_res = $conn->query("SELECT * FROM field_work_comments WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC LIMIT 50");
            if ($fw_res) { while ($fr = $fw_res->fetch_assoc()) { $fw_rows[] = $fr; } }
            $fw_unread = $conn->query("SELECT COUNT(*) as c FROM field_work_comments WHERE is_read=0");
            $fw_count  = $fw_unread ? (int)$fw_unread->fetch_assoc()['c'] : 0;
        } catch(Throwable $e) {}
    ?>
    <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1.5px solid #fbbf24;border-radius:24px;padding:24px 28px;margin-bottom:28px;box-shadow:0 8px 24px rgba(245,158,11,0.12);">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <h3 style="font-size:17px;font-weight:800;color:#92400e;margin:0;display:flex;align-items:center;gap:10px;">
                <span style="background:#f59e0b;color:white;width:36px;height:36px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:18px;">🏃</span>
                Field Work Messages
                <?php if($fw_count > 0): ?>
                <span style="background:#ef4444;color:white;font-size:11px;font-weight:800;padding:3px 9px;border-radius:99px;border:2px solid white;"><?php echo $fw_count; ?> NEW</span>
                <?php endif; ?>
            </h3>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button onclick="loadFieldWorkComments('today')" id="fwTodayBtn"
                    style="padding:8px 16px;border:none;border-radius:10px;background:#f59e0b;color:white;font-weight:700;font-size:13px;cursor:pointer;">Today</button>
                <button onclick="loadFieldWorkComments('all')" id="fwAllBtn"
                    style="padding:8px 16px;border:1.5px solid #fbbf24;border-radius:10px;background:white;color:#92400e;font-weight:700;font-size:13px;cursor:pointer;">All Time</button>
                <button onclick="markAllFwRead()"
                    style="padding:8px 16px;border:1.5px solid #fbbf24;border-radius:10px;background:white;color:#92400e;font-weight:700;font-size:13px;cursor:pointer;">✓ Mark All Read</button>
            </div>
        </div>
        <div id="fwCommentsList">
            <?php if(empty($fw_rows)): ?>
                <div style="text-align:center;color:#78350f;font-weight:600;padding:20px 0;opacity:0.7;">No field work messages for today.</div>
            <?php else: ?>
                <?php foreach($fw_rows as $fw): ?>
                <div class="fw-comment-card" data-id="<?php echo $fw['id']; ?>"
                    style="background:white;border-radius:16px;padding:16px 20px;margin-bottom:12px;border-left:4px solid <?php echo $fw['is_read'] ? '#fbbf24' : '#ef4444'; ?>;box-shadow:0 4px 12px rgba(0,0,0,0.06);display:flex;align-items:flex-start;gap:14px;justify-content:space-between;flex-wrap:wrap;">
                    <div style="flex:1;min-width:200px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                            <strong style="font-size:15px;color:#1e293b;"><?php echo htmlspecialchars($fw['full_name']); ?></strong>
                            <span style="font-size:11px;font-weight:700;background:#eef2ff;color:#4f46e5;padding:2px 8px;border-radius:99px;"><?php echo htmlspecialchars($fw['branch'] ?: 'No Branch'); ?></span>
                            <span style="font-size:11px;font-weight:700;background:<?php echo $fw['is_read'] ? '#f0fdf4' : '#fef2f2'; ?>;color:<?php echo $fw['is_read'] ? '#166534' : '#ef4444'; ?>;padding:2px 8px;border-radius:99px;"><?php echo $fw['is_read'] ? 'READ' : 'UNREAD'; ?></span>
                        </div>
                        <p style="margin:0;color:#334155;font-size:14px;line-height:1.6;"><?php echo nl2br(htmlspecialchars($fw['comment'])); ?></p>
                        <div style="font-size:11px;color:#94a3b8;font-weight:600;margin-top:8px;"><?php echo date('M d, Y h:i A', strtotime($fw['created_at'])); ?></div>
                    </div>
                    <?php if(!$fw['is_read']): ?>
                    <button onclick="markFwRead(<?php echo $fw['id']; ?>, this)"
                        style="padding:8px 14px;border:none;border-radius:10px;background:#10b981;color:white;font-weight:700;font-size:12px;cursor:pointer;white-space:nowrap;flex-shrink:0;">✓ Mark Read</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Audit Trail Panel (Admin) ── -->
    <div style="background:white;border:1px solid #e0e7ff;border-radius:24px;overflow:hidden;margin-bottom:28px;box-shadow:0 4px 24px rgba(79,70,229,0.09);">
        <button class="att-log-toggle" id="auditLogToggle" onclick="toggleAuditLog()" aria-expanded="false">
            <div class="att-log-toggle-icon" style="background:linear-gradient(135deg,#1e293b,#334155);">🛡️</div>
            <div class="att-log-toggle-text">
                <div class="att-log-toggle-title">Audit Trail</div>
                <div class="att-log-toggle-sub">System events log · Clock-ins, rejections, field work, blocked attempts</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <button onclick="event.stopPropagation();loadAuditLog('all')" style="padding:5px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:11px;font-weight:700;background:white;color:#475569;cursor:pointer;">All Time</button>
                <button onclick="event.stopPropagation();loadAuditLog('today')" style="padding:5px 12px;border:none;border-radius:8px;font-size:11px;font-weight:700;background:#1e293b;color:white;cursor:pointer;">Today</button>
            </div>
            <div class="att-log-chevron" id="auditLogChevron">▾</div>
        </button>
        <div class="att-log-body" id="auditLogBody">
            <div class="att-log-body-inner">
                <div id="auditLogContent" style="min-height:60px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-weight:600;">
                    Click "Today" or "All Time" to load the audit trail.
                </div>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 24px; display: flex; justify-content: flex-end; gap: 12px;">
        <a href="backup.php?action=download" target="_blank" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 14px; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3); display: inline-flex; align-items: center; gap: 8px; transition: transform 0.2s;">
            <span>💾</span> Download Backup
        </a>
        <a href="backup.php?action=email" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: white; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 14px; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3); display: inline-flex; align-items: center; gap: 8px; transition: transform 0.2s;" onclick="return confirm('Send the database backup to your email now?');">
            <span>✉️</span> Email Backup Now
        </a>
    </div>

    <!-- Hidden iframe to trigger auto backups seamlessly in the background if needed -->
    <iframe src="backup.php?action=auto" style="display:none;" title="Auto Backup Trigger"></iframe>

    <!-- ── Collapsible Attendance Log ── -->
    <?php
        // Pre-count stats for the toggle header
        $log_res = $conn->query("SELECT a.*, s.full_name, s.photo, COALESCE(NULLIF(TRIM(s.branch),''), 'No Branch') AS branch_name FROM attendance a JOIN staff s ON a.staff_id = s.staff_id ORDER BY a.id DESC LIMIT 200");
        $log_rows      = [];
        $log_working   = 0;
        $log_completed = 0;
        $log_missed    = 0;
        if ($log_res && !is_bool($log_res)) {
            while ($r = $log_res->fetch_assoc()) {
                $log_rows[] = $r;
                if (($r['status'] ?? '') === 'missed_out')   $log_missed++;
                elseif (!empty($r['clock_out']))             $log_completed++;
                else                                         $log_working++;
            }
        }
        $log_total = count($log_rows);
    ?>
    <div class="att-log-panel">
        <!-- Toggle Header -->
        <button class="att-log-toggle" id="attLogToggle" onclick="toggleAttLog()" aria-expanded="false" aria-controls="attLogBody">
            <div class="att-log-toggle-icon">📋</div>
            <div class="att-log-toggle-text">
                <div class="att-log-toggle-title">Staff Attendance Log</div>
                <div class="att-log-toggle-sub">Click to expand · Last 200 records · Search by name, ID or branch</div>
            </div>
            <div class="att-log-toggle-badges">
                <?php if ($log_working > 0): ?>
                <span class="att-log-badge green">🟢 <?php echo $log_working; ?> Working</span>
                <?php endif; ?>
                <?php if ($log_completed > 0): ?>
                <span class="att-log-badge blue">✅ <?php echo $log_completed; ?> Done</span>
                <?php endif; ?>
                <?php if ($log_missed > 0): ?>
                <span class="att-log-badge red">⚠️ <?php echo $log_missed; ?> Missed</span>
                <?php endif; ?>
            </div>
            <div class="att-log-chevron" id="attLogChevron">▾</div>
        </button>

        <!-- Collapsible Body -->
        <div class="att-log-body" id="attLogBody">
            <div class="att-log-body-inner">

                <!-- Search Box -->
                <div class="search-section" style="margin-bottom:0;">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="staffSearch" class="search-input"
                           style="margin-bottom:8px;"
                           placeholder="Search by name, staff ID, branch, or status…"
                           oninput="filterTable()" autocomplete="off">
                </div>
                <p class="search-hint" style="margin-bottom:16px;">💡 e.g. type <strong>Miss Dalemo</strong> or <strong>Head Office</strong> to filter that branch</p>

                <!-- Table -->
                <div class="table-scroll" style="border-radius:16px;overflow:hidden;border:1px solid #e0e7ff;">
                <table id="attendanceTable" class="responsive-table">
                    <thead style="background:linear-gradient(135deg,#4f46e5,#7c3aed);">
                        <tr>
                            <th style="color:white;padding:14px 16px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Staff Name</th>
                            <th style="color:white;padding:14px 16px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Staff ID</th>
                            <th style="color:white;padding:14px 16px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Branch</th>
                            <th style="color:white;padding:14px 16px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Clock In</th>
                            <th style="color:white;padding:14px 16px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Clock Out</th>
                            <th style="color:white;padding:14px 16px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Selfie</th>
                            <th style="color:white;padding:14px 16px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($log_total > 0) {
                        foreach ($log_rows as $idx => $row) {
                            $status_badge = 'badge-success';
                            $status_text  = 'Working';
                            if (($row['status'] ?? '') === 'missed_out') {
                                $status_badge = 'badge-danger';
                                $status_text  = 'missed(clockout)';
                            } elseif (!empty($row['clock_out'])) {
                                $status_badge = 'badge-info';
                                $status_text  = 'Completed';
                            }
                            $selfie      = $row['photo_in'] ?: $row['photo_out'];
                            $branch_disp = htmlspecialchars($row['branch_name']);
                            $row_bg      = ($idx % 2 === 0) ? '' : 'style="background:#fafbff;"';

                            echo "<tr data-branch='".strtoupper(htmlspecialchars($row['branch_name']))."' {$row_bg}>";
                            echo "<td data-label='Staff Name' style='padding:12px 16px;'>";
                            if ($row['photo']) echo "<img src='{$row['photo']}' class='staff-thumb'>";
                            echo "<strong>".htmlspecialchars($row['full_name'])."</strong></td>";
                            echo "<td data-label='Staff ID' style='padding:12px 16px;'><code style='background:#f1f5f9;padding:2px 7px;border-radius:6px;font-size:13px;'>".htmlspecialchars($row['staff_id'])."</code></td>";
                            echo "<td data-label='Branch' style='padding:12px 16px;'><span style='background:#eef2ff;color:#4f46e5;border-radius:8px;padding:3px 9px;font-size:12px;font-weight:700;'>{$branch_disp}</span></td>";
                            echo "<td data-label='Clock In' style='padding:12px 16px;font-size:13px;'>".date('M j, g:i A', strtotime($row['clock_in']))."</td>";
                            $clockOutLabel = '<span style="color:#94a3b8;">—</span>';
                            if (!empty($row['clock_out'])) {
                                $clockOutLabel = date('M j, g:i A', strtotime($row['clock_out']));
                                if (($row['status'] ?? '') === 'missed_out') $clockOutLabel .= " <small style='color:#ef4444;'>(missed clockout)</small>";
                            }
                            echo "<td data-label='Clock Out' style='padding:12px 16px;font-size:13px;'>".$clockOutLabel."</td>";
                            echo "<td data-label='Selfie' style='padding:12px 16px;'>";
                            if ($selfie) {
                                echo "<img src='{$selfie}' style='border-radius:10px;width:42px;height:42px;object-fit:cover;cursor:pointer;border:2px solid #e0e7ff;' onclick='showFull(\"{$selfie}\")'>";
                            } else {
                                echo "<span style='color:#cbd5e1;font-size:18px;'>🚫</span>";
                            }
                            echo "</td>";
                            echo "<td data-label='Status' style='padding:12px 16px;'><span class='badge {$status_badge}'>{$status_text}</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align:center;padding:32px;color:#94a3b8;'>No attendance records found</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
                </div>
                <!-- Row count -->
                <div style="margin-top:10px;font-size:12px;color:#94a3b8;font-weight:600;text-align:right;" id="attLogCount">
                    Showing <?php echo $log_total; ?> of <?php echo $log_total; ?> records
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="footer">
        &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Mbewu
    </div>
  </div>

  <!-- Admin Notification Toast (for incoming admin broadcasts) -->
  <div id="adminNotifToast" class="admin-notif-toast" role="alert" aria-live="polite">
      <button class="admin-notif-close" id="adminNotifClose" onclick="dismissAdminNotif()" aria-label="Close">✕</button>
      <div class="admin-notif-toast-header">📢 Message from Admin</div>
      <div class="admin-notif-toast-msg" id="adminNotifMsg"></div>
      <div class="admin-notif-toast-meta" id="adminNotifMeta"></div>
  </div>

<?php if ($staff_id): ?>
<script src="/asset/js/face-api.min.js?v=<?php echo $face_api_version; ?>"></script>
<script src="/asset/js/clock-face.js?v=<?php echo $clock_face_version; ?>"></script>
<?php endif; ?>
<?php if ($is_admin): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('attendanceChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Staff Present',
                        data: <?php echo json_encode($chart_data); ?>,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#4f46e5',
                        pointBorderColor: '#fff',
                        pointRadius: 5,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    });
</script>
<?php endif; ?>
<script>
    let video = null;
    let canvas = null;
    let clockBtn = null;
    let currentCoords = null;
    let locationWarmupPromise = null;

    let notificationTimeout = null;
    function showTopNotification(msg, isSuccess = false) {
        const notification = document.getElementById('topNotification');
        if (!notification) return;

        if (notificationTimeout) {
            clearTimeout(notificationTimeout);
        }
        notification.classList.remove('show', 'success', 'error');

        notification.classList.add(isSuccess ? 'success' : 'error');
        notification.innerHTML = (isSuccess ? '<span>✅</span> ' : '<span>⚠️</span> ') + msg;
        
        void notification.offsetWidth; // Force reflow
        notification.classList.add('show');

        notificationTimeout = setTimeout(() => {
            notification.classList.remove('show');
        }, 5000);
    }

    function showApiError(msg) {
        const overlay = document.getElementById('scanningOverlay');
        const cam = document.getElementById('camera-container');
        const result = document.getElementById('apiResult');
        if (overlay) overlay.style.display = 'none';
        if (cam) cam.style.borderColor = '#ef4444';
        if (result) {
            result.style.color = '#ef4444';
            result.innerHTML = '❌ ' + msg;
            setTimeout(() => {
                if (result && result.textContent.startsWith('❌')) result.innerHTML = '';
            }, 5000);
        }
        showTopNotification(msg, false);
    }

    function showApiSuccess(msg) {
        const result = document.getElementById('apiResult');
        if (result) {
            result.style.color = '#10b981';
            result.innerHTML = '✅ ' + msg;
        }
        showTopNotification(msg, true);
    }

    function getPosition(options) {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation not supported on this device'));
                return;
            }
            navigator.geolocation.getCurrentPosition(resolve, reject, options);
        });
    }

    function warmupLocation() {
        if (!navigator.geolocation || locationWarmupPromise) return locationWarmupPromise;
        locationWarmupPromise = getPosition({
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 120000
        }).then((pos) => {
            currentCoords = pos.coords;
            updateGeoStatusText(pos.coords);
            return pos;
        }).catch(() => null);
        return locationWarmupPromise;
    }

    async function getBestPosition() {
        try {
            const quick = await getPosition({
                enableHighAccuracy: true,
                timeout: 6000,
                maximumAge: 120000
            });
            currentCoords = quick.coords;
            updateGeoStatusText(quick.coords);
            return quick;
        } catch (_) {
            const fresh = await getPosition({
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0
            });
            currentCoords = fresh.coords;
            updateGeoStatusText(fresh.coords);
            return fresh;
        }
    }

    async function updateGeoStatusText(coords) {
        const geoBox = document.getElementById('gpsCoordsBox');
        const geoText = document.getElementById('liveGpsText');
        if (geoBox && geoText && coords) {
            geoBox.style.display = 'block';
            geoText.innerHTML = `Lat: ${coords.latitude.toFixed(6)}, Lng: ${coords.longitude.toFixed(6)}<br>Accuracy: ±${Math.round(coords.accuracy || 0)}m`;
        }
        const geoStatus = document.getElementById('geoStatus');
        if (geoStatus) {
            geoStatus.innerHTML = coords ? '📍 Location detected ✓' : '📍 Waiting for location permission...';
            geoStatus.style.color = coords ? '#10b981' : '';
        }

        if (coords && !window._autoDetectedBranch) {
            window._autoDetectedBranch = true;
            try {
                const fd = new FormData();
                fd.append('lat', coords.latitude);
                fd.append('lng', coords.longitude);
                const resp = await fetch('/api/check_location.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await resp.json();
                if (data.status === 'success') {
                    if (geoStatus) {
                        geoStatus.innerHTML = `📍 Auto-detected at branch: <strong>${data.branch}</strong>`;
                        geoStatus.style.color = '#10b981';
                    }
                    const regBtn = document.getElementById('registerLocBtn');
                    if (regBtn) regBtn.style.display = 'none';
                    
                    const helperText = regBtn ? regBtn.nextElementSibling : null;
                    if (helperText && helperText.tagName.toLowerCase() === 'p') {
                        helperText.style.display = 'none';
                    }
                }
            } catch (e) {}
        }
    }

    async function prepareFaceRecognition() {
        const statusEl = document.getElementById('faceStatus');
        const cameraContainer = document.getElementById('camera-container');
        if (!statusEl || typeof ClockFace === 'undefined') {
            throw new Error('Face verification library not available.');
        }

        statusEl.innerHTML = '🔄 Loading face recognition...';

        const profileResp = await fetch('/api/staff_profile.php', {
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const profileData = await profileResp.json();

        if (profileData.status !== 'success' || !profileData.photo) {
            throw new Error(profileData.message || 'Your profile photo is missing. Please contact admin.');
        }

        statusEl.innerHTML = '🔄 Matching with your saved profile photo...';
        await ClockFace.loadProfilePhoto(profileData.photo);
        await ClockFace.startLiveDetection(video, cameraContainer);

        statusEl.innerHTML = '✅ Face recognition ready. Look at the camera.';
        return true;
    }

    async function registerMyClockLocation() {
        if (!navigator.geolocation) {
            alert('GPS not supported on this device');
            return;
        }

        try {
            const pos = await getBestPosition();
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;

            const formData = new FormData();
            formData.append('lat', lat);
            formData.append('lng', lng);
            formData.append('sync_branch', '1');

            const resp = await fetch('/api/register_clock_location.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const data = await resp.json();

            if (data.status === 'success') {
                alert('✅ ' + data.message);
                if (data.branch_updated) {
                    alert(`📍 Branch "${data.branch_updated}" GPS updated to your current location!`);
                }
                location.reload();
            } else {
                alert('❌ ' + (data.message || 'Registration failed'));
            }
        } catch (err) {
            alert('GPS error: ' + err.message);
        }
    }

    async function processClocking(action) {
        // ── 6 PM Client-side cutoff ─────────────────────────────────
        const nowHour = new Date().getHours();
        if (nowHour >= 18) {
            showApiError('⏰ Clock-in and clock-out are disabled after 6:00 PM. Please contact your admin if you need assistance.');
            return;
        }

        if (typeof ClockFace === 'undefined') {
            showApiError('Face verification not loaded. Refresh the page.');
            return;
        }
        if (!ClockFace.isReady()) {
            showApiError('Face recognition is still loading. Please wait a moment.');
            return;
        }

        const overlay = document.getElementById('scanningOverlay');
        const cameraContainer = document.getElementById('camera-container');
        const btn = document.getElementById('clockBtn');
        if (!btn || !video || !canvas) return;

        overlay.style.display = 'block';
        cameraContainer.style.borderColor = '#3b82f6';
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Checking face & GPS…';

        let posResult;
        let faceResult;

        const [posSettled, faceSettled] = await Promise.allSettled([
            getBestPosition(),
            ClockFace.verifyVideoFace(video)
        ]);

        if (posSettled.status === 'fulfilled') {
            posResult = posSettled.value;
        } else {
            showApiError('GPS failed: ' + (posSettled.reason?.message || 'Please enable location access'));
            btn.disabled = false;
            btn.innerHTML = originalText;
            overlay.style.display = 'none';
            return;
        }

        if (faceSettled.status === 'fulfilled') {
            faceResult = faceSettled.value;
        } else {
            showApiError(faceSettled.reason?.message || 'Face verification failed.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            overlay.style.display = 'none';
            return;
        }

        if (!faceResult.match) {
            showApiError(faceResult.message || 'Face does not match your profile photo.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            overlay.style.display = 'none';
            setTimeout(() => { cameraContainer.style.borderColor = '#e2e8f0'; }, 2000);
            return;
        }

        const ctx = canvas.getContext('2d');
        canvas.width = 320;
        canvas.height = 240;
        ctx.drawImage(video, 0, 0, 320, 240);
        const photoData = canvas.toDataURL('image/jpeg', 0.6);

        btn.innerHTML = 'Saving attendance…';

        const formData = new FormData();
        formData.append('action', action);
        formData.append('lat', posResult.coords.latitude);
        formData.append('lng', posResult.coords.longitude);
        formData.append('gps_accuracy', String(posResult.coords.accuracy || 0));
        formData.append('photo', photoData);
        formData.append('face_verified', '1');
        formData.append('face_distance', String(faceResult.distance));

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 30000);

        try {
            const res = await fetch('/api/web_clock.php', {
                method: 'POST',
                body: formData,
                signal: controller.signal,
                credentials: 'same-origin'
            });
            clearTimeout(timeoutId);

            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                throw new Error('Server returned an invalid response. Try again.');
            }

            overlay.style.display = 'none';
            if (data.status === 'success') {
                cameraContainer.style.borderColor = '#10b981';
                showApiSuccess(data.message);
                btn.innerHTML = 'Verified!';
                setTimeout(() => location.reload(), 1200);
            } else {
                let errMsg = data.message || 'Verification failed.';
                if (data.debug && data.debug.dist) {
                    errMsg += ' Distance: ' + data.debug.dist + 'm';
                }
                showApiError(errMsg);
                btn.disabled = false;
                btn.innerHTML = originalText;
                setTimeout(() => { cameraContainer.style.borderColor = '#e2e8f0'; }, 2000);
            }
        } catch (err) {
            clearTimeout(timeoutId);
            const msg = err.name === 'AbortError'
                ? 'Request timed out. Check your connection.'
                : (err.message || 'Network error. Please try again.');
            showApiError(msg);
            btn.disabled = false;
            btn.innerHTML = originalText;
            overlay.style.display = 'none';
        }
    }

    function filterTable() {
        const input = document.getElementById("staffSearch");
        if (!input) return;
        const filter = input.value.trim().toUpperCase();
        const table = document.getElementById("attendanceTable");
        if (!table) return;
        const rows = table.getElementsByTagName("tr");
        let visibleCount = 0;
        for (let i = 1; i < rows.length; i++) {
            if (!filter) { rows[i].style.display = ""; visibleCount++; continue; }
            let found = false;
            // Check branch attribute first (fast branch-level search)
            const branchAttr = (rows[i].getAttribute('data-branch') || '').toUpperCase();
            if (branchAttr && branchAttr.indexOf(filter) > -1) { found = true; }
            // Also search all cells
            if (!found) {
                const tds = rows[i].getElementsByTagName("td");
                for (let j = 0; j < tds.length; j++) {
                    if (tds[j] && tds[j].textContent.toUpperCase().indexOf(filter) > -1) { found = true; break; }
                }
            }
            rows[i].style.display = found ? "" : "none";
            if (found) visibleCount++;
        }
        // Update search hint
        const hint = document.querySelector('.search-hint');
        const countEl = document.getElementById('attLogCount');
        const totalRows = rows.length - 1;
        if (hint && filter) {
            hint.textContent = `\uD83D\uDD0E Showing ${visibleCount} result${visibleCount !== 1 ? 's' : ''} for "${input.value}"`;
        } else if (hint) {
            hint.textContent = '\uD83D\uDCA1 e.g. type Miss Dalemo or Head Office to filter that branch';
        }
        if (countEl) {
            countEl.textContent = `Showing ${visibleCount} of ${totalRows} records`;
        }
    }

    // ── Toggle Attendance Log Panel ─────────────────────────────────
    function toggleAttLog() {
        const body    = document.getElementById('attLogBody');
        const chevron = document.getElementById('attLogChevron');
        const btn     = document.getElementById('attLogToggle');
        if (!body) return;
        const isOpen = body.classList.contains('open');
        if (isOpen) {
            body.classList.remove('open');
            if (chevron) chevron.classList.remove('open');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        } else {
            body.classList.add('open');
            if (chevron) chevron.classList.add('open');
            if (btn) btn.setAttribute('aria-expanded', 'true');
            // Auto-focus search when opening
            setTimeout(() => {
                const s = document.getElementById('staffSearch');
                if (s) s.focus();
            }, 460);
        }
    }

    // ── Admin Broadcast ──────────────────────────────────────────────
    async function sendBroadcast() {
        const target  = document.getElementById('broadcastTarget')?.value || 'all';
        const message = document.getElementById('broadcastMsg')?.value?.trim();
        const status  = document.getElementById('broadcastStatus');
        const btn     = document.getElementById('broadcastSendBtn');
        if (!message) {
            if (status) { status.style.color = '#ef4444'; status.textContent = '⚠️ Please type a message first.'; }
            return;
        }
        if (btn) btn.disabled = true;
        if (status) { status.style.color = '#7c3aed'; status.textContent = '⏳ Sending…'; }
        try {
            const fd = new FormData();
            fd.append('action',  'send');
            fd.append('message', message);
            fd.append('target',  target);
            const res  = await fetch('/api/notifications.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await res.json();
            if (data.status === 'success') {
                if (status) { status.style.color = '#10b981'; status.textContent = '✅ Notification sent successfully!'; }
                document.getElementById('broadcastMsg').value = '';
                setTimeout(() => { if (status) status.textContent = ''; }, 4000);
            } else {
                if (status) { status.style.color = '#ef4444'; status.textContent = '❌ ' + (data.message || 'Failed to send'); }
            }
        } catch(e) {
            if (status) { status.style.color = '#ef4444'; status.textContent = '❌ Network error. Try again.'; }
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    // ── Staff Notification Poller ───────────────────────────────────
    let _pendingNotifs = [];
    let _notifIndex    = 0;
    let _currentNotifId = null;

    async function pollAdminNotifications() {
        try {
            const res  = await fetch('/api/notifications.php?action=fetch', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.status === 'success' && Array.isArray(data.notifications) && data.notifications.length > 0) {
                _pendingNotifs = data.notifications;
                _notifIndex    = 0;
                showNextNotif();
            }
        } catch (_) {}
    }

    function showNextNotif() {
        if (_notifIndex >= _pendingNotifs.length) return;
        const n = _pendingNotifs[_notifIndex];
        _currentNotifId = n.id;
        const toast   = document.getElementById('adminNotifToast');
        const msgEl   = document.getElementById('adminNotifMsg');
        const metaEl  = document.getElementById('adminNotifMeta');
        if (!toast || !msgEl) return;
        msgEl.textContent  = n.message;
        if (metaEl) metaEl.textContent = 'From ' + n.created_by + ' · ' + n.created_at;
        toast.classList.add('show');
        // Auto-dismiss after 30 seconds then show next
        setTimeout(() => { dismissAdminNotif(); }, 30000);
    }

    function dismissAdminNotif() {
        const toast = document.getElementById('adminNotifToast');
        if (toast) toast.classList.remove('show');
        if (_currentNotifId) {
            const fd = new FormData();
            fd.append('action', 'dismiss');
            fd.append('id', _currentNotifId);
            fetch('/api/notifications.php', { method: 'POST', body: fd, credentials: 'same-origin' }).catch(() => {});
            _currentNotifId = null;
        }
        _notifIndex++;
        setTimeout(() => { showNextNotif(); }, 600);
    }

    // ── Field Work Comment Submission (Staff) ───────────────────────
    async function submitFieldWorkComment() {
        const msg = document.getElementById('fieldWorkMsg');
        const btn = document.getElementById('fieldWorkBtn');
        const status = document.getElementById('fieldWorkStatus');
        if (!msg || !msg.value.trim()) {
            if (status) { status.style.color = '#ef4444'; status.textContent = '⚠️ Please type your field work note first.'; }
            return;
        }
        if (btn) btn.disabled = true;
        if (status) { status.style.color = '#7c3aed'; status.textContent = '⏳ Submitting...'; }
        try {
            const fd = new FormData();
            fd.append('action', 'submit');
            fd.append('comment', msg.value.trim());
            const res = await fetch('/api/field_work_comment.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await res.json();
            if (data.status === 'success') {
                if (status) { status.style.color = '#10b981'; status.textContent = '✅ ' + data.message; }
                msg.value = '';
                showTopNotification('Field work note submitted successfully!', true);
            } else {
                if (status) { status.style.color = '#ef4444'; status.textContent = '❌ ' + (data.message || 'Failed.'); }
            }
        } catch(e) {
            if (status) { status.style.color = '#ef4444'; status.textContent = '❌ Network error. Try again.'; }
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    // ── Admin: Load Field Work Comments ────────────────────────────
    async function loadFieldWorkComments(filter) {
        const list = document.getElementById('fwCommentsList');
        if (!list) return;
        list.innerHTML = '<div style="text-align:center;padding:20px;color:#78350f;font-weight:600;">⏳ Loading...</div>';
        try {
            const res = await fetch('/api/field_work_comment.php?action=fetch&filter=' + encodeURIComponent(filter), { credentials: 'same-origin' });
            const data = await res.json();
            if (data.status === 'success') {
                if (!data.comments || data.comments.length === 0) {
                    list.innerHTML = '<div style="text-align:center;color:#78350f;font-weight:600;padding:20px;opacity:0.7;">No field work messages found.</div>';
                    return;
                }
                list.innerHTML = data.comments.map(fw => `
                    <div class="fw-comment-card" data-id="${fw.id}"
                        style="background:white;border-radius:16px;padding:16px 20px;margin-bottom:12px;border-left:4px solid ${fw.is_read == 1 ? '#fbbf24' : '#ef4444'};box-shadow:0 4px 12px rgba(0,0,0,0.06);display:flex;align-items:flex-start;gap:14px;justify-content:space-between;flex-wrap:wrap;">
                        <div style="flex:1;min-width:200px;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                                <strong style="font-size:15px;color:#1e293b;">${fw.full_name || 'Unknown'}</strong>
                                <span style="font-size:11px;font-weight:700;background:#eef2ff;color:#4f46e5;padding:2px 8px;border-radius:99px;">${fw.branch || 'No Branch'}</span>
                                <span style="font-size:11px;font-weight:700;background:${fw.is_read == 1 ? '#f0fdf4' : '#fef2f2'};color:${fw.is_read == 1 ? '#166534' : '#ef4444'};padding:2px 8px;border-radius:99px;">${fw.is_read == 1 ? 'READ' : 'UNREAD'}</span>
                            </div>
                            <p style="margin:0;color:#334155;font-size:14px;line-height:1.6;">${fw.comment.replace(/\n/g,'<br>')}</p>
                            <div style="font-size:11px;color:#94a3b8;font-weight:600;margin-top:8px;">${new Date(fw.created_at).toLocaleString()}</div>
                        </div>
                        ${fw.is_read == 0 ? `<button onclick="markFwRead(${fw.id}, this)" style="padding:8px 14px;border:none;border-radius:10px;background:#10b981;color:white;font-weight:700;font-size:12px;cursor:pointer;white-space:nowrap;flex-shrink:0;">✓ Mark Read</button>` : ''}
                    </div>
                `).join('');
            } else {
                list.innerHTML = '<div style="color:#ef4444;text-align:center;padding:20px;">Failed to load: ' + (data.message || '') + '</div>';
            }
        } catch(e) {
            list.innerHTML = '<div style="color:#ef4444;text-align:center;padding:20px;">Network error.</div>';
        }
    }

    async function markFwRead(id, btn) {
        if (btn) btn.disabled = true;
        const fd = new FormData();
        fd.append('action', 'mark_read');
        fd.append('id', id);
        await fetch('/api/field_work_comment.php', { method: 'POST', body: fd, credentials: 'same-origin' }).catch(()=>{});
        // Update UI
        const card = document.querySelector(`.fw-comment-card[data-id="${id}"]`);
        if (card) {
            card.style.borderLeftColor = '#fbbf24';
            const badges = card.querySelectorAll('span');
            badges.forEach(s => { if (s.textContent.trim() === 'UNREAD') { s.textContent = 'READ'; s.style.background='#f0fdf4'; s.style.color='#166534'; } });
            if (btn) btn.remove();
        }
    }

    async function markAllFwRead() {
        const fd = new FormData();
        fd.append('action', 'mark_all_read');
        await fetch('/api/field_work_comment.php', { method: 'POST', body: fd, credentials: 'same-origin' }).catch(()=>{});
        loadFieldWorkComments('today');
    }

    // ── Admin: Audit Trail ──────────────────────────────────────────
    function toggleAuditLog() {
        const body    = document.getElementById('auditLogBody');
        const chevron = document.getElementById('auditLogChevron');
        const btn     = document.getElementById('auditLogToggle');
        if (!body) return;
        const isOpen = body.classList.contains('open');
        if (isOpen) {
            body.classList.remove('open');
            if (chevron) chevron.classList.remove('open');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        } else {
            body.classList.add('open');
            if (chevron) chevron.classList.add('open');
            if (btn) btn.setAttribute('aria-expanded', 'true');
            loadAuditLog('today');
        }
    }

    const EVENT_ICONS = {
        'clock_in': '✅', 'clock_out': '🏁', 'blocked_after_6pm': '⏰',
        'location_rejected': '📍', 'face_rejected': '❌', 'field_work_comment': '🏃',
        'default': '📋'
    };
    const EVENT_COLORS = {
        'clock_in': '#10b981', 'clock_out': '#3b82f6', 'blocked_after_6pm': '#f59e0b',
        'location_rejected': '#ef4444', 'face_rejected': '#ef4444', 'field_work_comment': '#f59e0b',
        'default': '#64748b'
    };

    async function loadAuditLog(filter) {
        const content = document.getElementById('auditLogContent');
        if (!content) return;
        content.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">⏳ Loading audit trail...</div>';

        const body = document.getElementById('auditLogBody');
        if (body && !body.classList.contains('open')) {
            body.classList.add('open');
            const chevron = document.getElementById('auditLogChevron');
            if (chevron) chevron.classList.add('open');
        }

        try {
            const res = await fetch('/api/audit_log.php?action=fetch&filter=' + encodeURIComponent(filter) + '&limit=100', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.status === 'success') {
                if (!data.logs || data.logs.length === 0) {
                    content.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">No audit records found for this period.</div>';
                    return;
                }
                content.innerHTML = `
                    <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="padding:10px 14px;text-align:left;font-weight:700;color:#64748b;font-size:11px;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">Event</th>
                                <th style="padding:10px 14px;text-align:left;font-weight:700;color:#64748b;font-size:11px;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">Staff</th>
                                <th style="padding:10px 14px;text-align:left;font-weight:700;color:#64748b;font-size:11px;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">Detail</th>
                                <th style="padding:10px 14px;text-align:left;font-weight:700;color:#64748b;font-size:11px;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">Time</th>
                                <th style="padding:10px 14px;text-align:left;font-weight:700;color:#64748b;font-size:11px;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.logs.map((log, i) => {
                                const icon  = EVENT_ICONS[log.event_type] || EVENT_ICONS['default'];
                                const color = EVENT_COLORS[log.event_type] || EVENT_COLORS['default'];
                                const rowBg = i % 2 === 0 ? '' : 'background:#fafbff;';
                                return `<tr style="${rowBg}">
                                    <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;">
                                        <span style="background:${color}22;color:${color};padding:3px 9px;border-radius:99px;font-size:11px;font-weight:700;white-space:nowrap;">${icon} ${log.event_type.replace(/_/g,' ')}</span>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;font-weight:600;">
                                        ${log.full_name || ''} <span style="color:#94a3b8;font-size:11px;">(${log.staff_id || '-'})</span>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;color:#475569;max-width:260px;word-break:break-word;">${log.event_detail || '-'}</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;color:#64748b;white-space:nowrap;">${new Date(log.created_at).toLocaleString()}</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;color:#94a3b8;font-size:11px;">${log.ip_address || '-'}</td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                    </div>
                    <div style="font-size:12px;color:#94a3b8;font-weight:600;text-align:right;margin-top:8px;padding-right:14px;">
                        Showing ${data.logs.length} records ${filter === 'today' ? 'for today' : '(all time)'}
                    </div>
                `;
            } else {
                content.innerHTML = '<div style="color:#ef4444;text-align:center;padding:20px;">' + (data.message || 'Failed to load audit trail') + '</div>';
            }
        } catch(e) {
            content.innerHTML = '<div style="color:#ef4444;text-align:center;padding:20px;">Network error loading audit trail.</div>';
        }
    }

    function showFull(src) { window.open(src, '_blank'); }
    
    function updateClock() {
        const now = new Date();
        
        let h = now.getHours();
        let m = now.getMinutes();
        let s = now.getSeconds();
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        h = h ? h : 12;
        
        m = m < 10 ? '0' + m : m;
        s = s < 10 ? '0' + s : s;
        
        const liveTime = document.getElementById('liveTime');
        const liveAmPm = document.getElementById('liveAmPm');
        if(liveTime) liveTime.textContent = h + ':' + m + ':' + s;
        if(liveAmPm) liveAmPm.textContent = ampm;
        
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        const liveDayName = document.getElementById('liveDayName');
        const liveDateStr = document.getElementById('liveDateStr');
        
        if(liveDayName) liveDayName.textContent = days[now.getDay()];
        if(liveDateStr) liveDateStr.textContent = months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
    }
    
    async function init() {
        video = document.getElementById('video');
        canvas = document.getElementById('canvas');
        clockBtn = document.getElementById('clockBtn');
        const faceStatus = document.getElementById('faceStatus');

        if (!video) return;

        try {
            if (faceStatus) faceStatus.innerHTML = '🔄 Starting camera...';

            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                },
                audio: false
            });
            video.srcObject = stream;
            await video.play();

            warmupLocation();
            const success = await prepareFaceRecognition();
            if (success && clockBtn && !clockBtn.hasAttribute('data-prevent-enable')) {
                clockBtn.disabled = false;
            }
        } catch (err) {
            console.error('Camera error:', err);
            if (faceStatus) {
                faceStatus.innerHTML = err && err.message
                    ? '❌ ' + err.message
                    : '❌ Camera access denied. Please allow camera access.';
            }
        }
    }
    
    const timeToday = "<?php echo formatHours($th_today); ?>";
    const timeYest = "<?php echo formatHours($th_yest); ?>";
    let showingToday = true;
    
    function toggleDayTracked() {
        const label = document.getElementById('timeTrackedLabel');
        const val = document.getElementById('timeTrackedValue');
        const icon = document.getElementById('timeTrackedIcon');
        
        if (showingToday) {
            label.textContent = "Yesterday";
            val.textContent = timeYest;
            if(icon) icon.innerHTML = '<path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>';
            showingToday = false;
        } else {
            label.textContent = "Today";
            val.textContent = timeToday;
            if(icon) icon.innerHTML = '<path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>';
            showingToday = true;
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        updateClock();
        setInterval(updateClock, 1000);
        <?php if ($staff_id): ?>
        init();
        <?php endif; ?>
        // Poll for admin notifications every 30 seconds
        pollAdminNotifications();
        setInterval(pollAdminNotifications, 30000);
    });
</script>
<!-- Inbox Modal -->
<div class="dashboard-modal-overlay" id="inboxModalOverlay" onclick="if(event.target===this) toggleInboxModal()">
    <div class="dashboard-modal">
        <div class="dashboard-modal-header">
            <div class="dashboard-modal-title">Notifications Inbox</div>
            <div class="dashboard-modal-close" onclick="toggleInboxModal()">✕</div>
        </div>
        <div class="dashboard-modal-body">
            <div id="inboxLoading" style="padding: 40px; text-align: center; color: var(--text-muted);">
                ⏳ Loading notifications...
            </div>
            <ul class="dashboard-modal-list" id="inboxList" style="display: none;">
            </ul>
        </div>
    </div>
</div>

<script>
    function toggleInboxModal() {
        const overlay = document.getElementById('inboxModalOverlay');
        if (overlay.classList.contains('show')) {
            overlay.classList.remove('show');
        } else {
            overlay.classList.add('show');
            loadInbox();
        }
    }

    function loadInbox() {
        const list = document.getElementById('inboxList');
        const loading = document.getElementById('inboxLoading');
        const badge = document.getElementById('inboxBadge');
        
        list.style.display = 'none';
        loading.style.display = 'block';
        
        fetch('/api/notifications.php?action=fetch_inbox', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                loading.style.display = 'none';
                if (res.status === 'success' && res.notifications) {
                    list.innerHTML = '';
                    if (res.notifications.length === 0) {
                        list.innerHTML = '<li style="justify-content:center;color:var(--text-muted);padding:30px;">No notifications found.</li>';
                    } else {
                        res.notifications.forEach(n => {
                            let isUnread = !n.read_at;
                            let li = document.createElement('li');
                            li.style.flexDirection = 'column';
                            li.style.alignItems = 'flex-start';
                            li.style.gap = '8px';
                            if (isUnread) li.style.background = 'var(--surface-alt)';
                            
                            let dateStr = new Date(n.created_at).toLocaleString();
                            
                            li.innerHTML = `
                                <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                                    <span style="font-weight:700; font-size:15px; color:var(--text);">${n.created_by}</span>
                                    <span style="font-size:12px; color:var(--text-muted); font-weight:600;">${dateStr}</span>
                                </div>
                                <div style="font-size:14px; color:var(--text); line-height:1.5;">${n.message}</div>
                                ${isUnread ? '<div style="font-size:11px; font-weight:800; color:#10b981; margin-top:4px;">NEW</div>' : '<div style="font-size:11px; font-weight:700; color:var(--text-muted); margin-top:4px;">READ</div>'}
                            `;
                            
                            // Mark as read if unread
                            if (isUnread) {
                                li.onclick = () => {
                                    const fd = new FormData();
                                    fd.append('action', 'dismiss');
                                    fd.append('id', n.id);
                                    fetch('/api/notifications.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                                        .then(() => loadInbox()); // reload to update badge & status
                                };
                                li.style.cursor = 'pointer';
                            }
                            
                            list.appendChild(li);
                        });
                    }
                    list.style.display = 'block';
                    
                    if (badge) {
                        if (res.unread_count > 0) {
                            badge.style.display = 'block';
                            badge.textContent = res.unread_count;
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(err => {
                loading.style.display = 'none';
                list.innerHTML = '<li style="color:#ef4444;justify-content:center;">Failed to load inbox.</li>';
                list.style.display = 'block';
            });
    }
    
    // Initial badge load
    document.addEventListener('DOMContentLoaded', () => {
        fetch('/api/notifications.php?action=fetch_inbox', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                const badge = document.getElementById('inboxBadge');
                if (badge && res.status === 'success' && res.unread_count > 0) {
                    badge.style.display = 'block';
                    badge.textContent = res.unread_count;
                }
            }).catch(e => {});
    });
</script>

<!-- Dashboard Details Modal -->
<div class="dashboard-modal-overlay" id="dashboardDetailsOverlay" onclick="if(event.target===this) closeDashboardDetailsModal()">
    <div class="dashboard-modal">
        <div class="dashboard-modal-header">
            <div class="dashboard-modal-title" id="dashboardDetailsTitle">Details</div>
            <div class="dashboard-modal-close" onclick="closeDashboardDetailsModal()">✕</div>
        </div>
        <div class="dashboard-modal-body">
            <div id="dashboardDetailsLoading" style="padding: 40px; text-align: center; color: var(--text-muted);">
                ⏳ Loading...
            </div>
            <ul class="dashboard-modal-list" id="dashboardDetailsList" style="display: none;">
            </ul>
        </div>
    </div>
</div>

<script>
    function openDashboardDetailsModal(type, branch = '') {
        const overlay = document.getElementById('dashboardDetailsOverlay');
        const title = document.getElementById('dashboardDetailsTitle');
        const list = document.getElementById('dashboardDetailsList');
        const loading = document.getElementById('dashboardDetailsLoading');
        
        let titleText = 'Details';
        if (type === 'total_staff') titleText = 'Total Staff';
        if (type === 'present_today') titleText = 'Present Today';
        if (type === 'absent_today') titleText = 'Absent Today';
        if (type === 'total_branches') titleText = 'Branches';
        if (type === 'branch_today_in') titleText = branch + ' - Today In';
        if (type === 'branch_today_out') titleText = branch + ' - Today Out';
        if (type === 'branch_week_in') titleText = branch + ' - Week In';
        if (type === 'branch_week_out') titleText = branch + ' - Week Out';
        
        title.innerText = titleText;
        list.innerHTML = '';
        list.style.display = 'none';
        loading.style.display = 'block';
        
        overlay.classList.add('show');
        
        fetch('/api/get_dashboard_details.php?type=' + encodeURIComponent(type) + '&branch=' + encodeURIComponent(branch))
            .then(r => r.json())
            .then(res => {
                loading.style.display = 'none';
                if (res.status === 'success' && res.data) {
                    if (res.data.length === 0) {
                        list.innerHTML = '<li style="justify-content:center;color:var(--text-muted);padding:30px;">No records found.</li>';
                    } else {
                        res.data.forEach(item => {
                            let li = document.createElement('li');
                            let html = `<div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:40px;height:40px;border-radius:50%;background:var(--primary-light);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">
                                    ${item.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:15px;">${item.name} <span style="font-size:12px;color:var(--text-muted);font-weight:600;">(${item.id})</span></div>
                                    <div style="font-size:13px;color:var(--text-muted);margin-top:2px;">
                                        <span style="background:var(--surface-alt);padding:2px 8px;border-radius:12px;font-weight:600;font-size:11px;">${item.branch}</span>
                                        ${item.dept ? ' · ' + item.dept : ''}
                                    </div>
                                </div>
                            </div>`;
                            
                            if (item.time_in || item.time_out) {
                                html += `<div style="text-align:right;">
                                    <div style="font-size:13px;font-weight:700;color:#10b981;">IN: ${item.time_in || '-'}</div>
                                    <div style="font-size:13px;font-weight:700;color:#f59e0b;margin-top:2px;">OUT: ${item.time_out || '-'}</div>
                                </div>`;
                            } else if (item.status === 'absent') {
                                html += `<div style="text-align:right;"><span style="background:#fef2f2;color:#ef4444;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:700;border:1px solid #fecaca;">Absent</span></div>`;
                            }
                            li.innerHTML = html;
                            list.appendChild(li);
                        });
                    }
                    list.style.display = 'block';
                } else {
                    list.innerHTML = '<li style="color:#ef4444;justify-content:center;">Error loading data: ' + (res.message || 'Unknown error') + '</li>';
                    list.style.display = 'block';
                }
            })
            .catch(err => {
                loading.style.display = 'none';
                list.innerHTML = '<li style="color:#ef4444;justify-content:center;">Failed to connect to server.</li>';
                list.style.display = 'block';
            });
    }
    
    function closeDashboardDetailsModal() {
        document.getElementById('dashboardDetailsOverlay').classList.remove('show');
    }
</script>

<script>
    function formatHoursJS(decimal) {
        if (!decimal || decimal <= 0) return "0min";
        const h = Math.floor(decimal);
        const m = Math.floor((decimal - h) * 60);
        const s = Math.round(((decimal - h) * 60 - m) * 60);
        let str = "";
        if (h > 0) str += `${h}hrs `;
        if (m > 0 || (h === 0 && s === 0)) str += `${m}min`;
        if (s > 0) str += ` ${s}sec`;
        return str.trim();
    }

    document.addEventListener("DOMContentLoaded", () => {
        const animateElements = document.querySelectorAll('.animate-number');
        const animateHours = document.querySelectorAll('.animate-hours');
        const duration = 1500; // ms
        
        const startAnimation = (el, isHours) => {
            if (el.dataset.animated) return;
            el.dataset.animated = "true";
            
            const target = parseFloat(el.getAttribute('data-target')) || 0;
            const startTime = performance.now();
            
            const update = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                // easeOutQuart
                const ease = 1 - Math.pow(1 - progress, 4);
                const currentVal = target * ease;
                
                if (isHours) {
                    el.textContent = formatHoursJS(currentVal);
                } else {
                    el.textContent = Math.round(currentVal);
                }
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    if (isHours) el.textContent = formatHoursJS(target);
                    else el.textContent = Math.round(target);
                }
            };
            requestAnimationFrame(update);
        };
        
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const isHours = el.classList.contains('animate-hours');
                    startAnimation(el, isHours);
                    obs.unobserve(el);
                }
            });
        }, { threshold: 0.1 });
        
        animateElements.forEach(el => observer.observe(el));
        animateHours.forEach(el => observer.observe(el));
    });
</script>

<script src="/asset/js/idle-logout.js?v=<?php echo $idle_logout_version; ?>" defer></script>
</body>
</html>



