<?php
include(__DIR__ . "/../includes/config.php");

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

$is_admin = isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin');
$staff_id = $_SESSION['staff_id'] ?? null;
$display_name = $_SESSION['admin'] ?? 'User';
$staff_profile_photo = '';

if ($staff_id) {
    $photo_stmt = $conn->prepare("SELECT photo FROM staff WHERE staff_id = ? LIMIT 1");
    if ($photo_stmt) {
        $photo_stmt->bind_param("s", $staff_id);
        $photo_stmt->execute();
        $photo_row = $photo_stmt->get_result()->fetch_assoc();
        $staff_profile_photo = $photo_row['photo'] ?? '';
        $photo_stmt->close();
    }
}

// TEMPORARY: Enable errors if something is failing
if ($is_admin) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

try {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Attendance System</title>
  <link rel="stylesheet" href="/asset/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
    
    .dashboard-header {
        background: linear-gradient(135deg, #1e293b, #334155);
        color: white; padding: 40px; border-radius: 24px; margin-bottom: 30px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); position: relative; overflow: hidden;
    }
    .dashboard-header h2 { font-size: 32px; font-weight: 800; margin:0; }

    .clocking-card {
        background: white; padding: 30px; border-radius: 24px; margin-bottom: 30px;
        border: 1px solid var(--border); box-shadow: var(--shadow);
    }
    
    #camera-container {
        width: 100%; max-width: 280px; margin: 0 auto 20px;
        border-radius: 50%; overflow: hidden; background: #000;
        aspect-ratio: 1 / 1; position: relative; border: 6px solid #e2e8f0;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        transition: border-color 0.3s ease;
    }
    #video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
    #canvas { display: none; }
    
    .scanning-overlay {
        position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        border-radius: 50%; box-shadow: inset 0 0 0 10px rgba(59, 130, 246, 0.5);
        animation: pulse 1.5s infinite; display: none; z-index: 10;
    }
    @keyframes pulse { 0% { transform: scale(0.95); opacity: 0.5; } 50% { transform: scale(1); opacity: 1; } 100% { transform: scale(0.95); opacity: 0.5; } }

    .clock-btn {
        background: var(--primary); color: white; border: none;
        padding: 18px 50px; font-size: 18px; font-weight: 700;
        border-radius: 16px; cursor: pointer; transition: all 0.3s var(--ease);
        box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
    }
    .clock-btn.out { background: #ef4444; box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3); }
    .clock-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; box-shadow: none; }

    .search-input {
        width: 100%; padding: 16px 24px 16px 50px;
        background: white; border: 1px solid var(--border);
        border-radius: 16px; font-size: 16px; margin-bottom: 30px;
        box-shadow: var(--shadow-sm);
    }

    .recent-table {
        background: white; border-radius: 24px; padding: 10px;
        border: 1px solid var(--border); box-shadow: var(--shadow-sm);
    }
    th { text-align: left; padding: 18px 20px; font-size: 14px; color: #1e293b; font-weight: 800; border-bottom: 2px solid #f1f5f9; background: #f8fafc; }
    td { padding: 16px 20px; font-size: 15px; color: #334155; border-bottom: 1px solid #f1f5f9; }
    .staff-thumb { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 12px; vertical-align: middle; }
  </style>
</head>
<body>

  <?php include(__DIR__ . "/../includes/sidebar.php"); ?>

  <div class="content">
    <div class="dashboard-header">
        <h2>Welcome, <?php echo htmlspecialchars($display_name); ?> 👋</h2>
        <p>System operational. Facial verification active.</p>
    </div>

    <?php if ($staff_id): ?>
    <div class="clocking-card" style="text-align:center;">
        <h3>📸 Face Clocking (Geofenced)</h3>
        <p style="color:var(--text-muted); margin-bottom:20px;">Please center your face in the camera frame.</p>
        
        <div id="camera-container">
            <video id="video" autoplay playsinline></video>
            <div id="scanningOverlay" class="scanning-overlay"></div>
            <canvas id="canvas" width="640" height="480"></canvas>
        </div>

        <?php
            $is_clocked_in = false;
            $stmt = $conn->prepare("SELECT id FROM `attendance` WHERE staff_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $staff_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $is_clocked_in = true;
                }
                $stmt->close();
            }
        ?>
        
        <div id="clockControls">
            <?php if (!$is_clocked_in): ?>
                <button id="clockBtn" class="clock-btn" disabled onclick="processClocking('clock_in')">Verify & Clock In</button>
            <?php else: ?>
                <button id="clockBtn" class="clock-btn out" disabled onclick="processClocking('clock_out')">Verify & Clock Out</button>
            <?php endif; ?>
        </div>
        
        <p id="faceStatus" style="margin-top:8px; color:var(--text-muted); font-size:14px;">🔄 Loading face recognition...</p>
        <p id="geoStatus" style="margin-top:8px; color:var(--text-muted); font-size:14px;">📍 Detecting location...</p>
        <div id="apiResult" style="margin-top:15px; font-weight:600; font-size:18px;"></div>
    </div>
    
    <div class="recent-table" style="margin-top: 30px;">
        <h3 style="margin: 20px; font-size: 18px; color: var(--text);">🕒 Your Recent Attendance</h3>
        <table id="staffAttendanceTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->prepare("SELECT clock_in, clock_out, status FROM attendance WHERE staff_id = ? ORDER BY clock_in DESC LIMIT 5");
                if ($stmt) {
                    $stmt->bind_param("s", $staff_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) {
                        $in = date("M d, Y h:i A", strtotime($r['clock_in']));
                        $out = $r['clock_out'] ? date("h:i A", strtotime($r['clock_out'])) : '—';
                        $stat = "<span class='badge badge-" . ($r['status']=='in' ? 'success' : 'warning') . "'>" . strtoupper($r['status']) . "</span>";
                        echo "<tr><td>".date("M d, Y", strtotime($r['clock_in']))."</td><td>".date("h:i A", strtotime($r['clock_in']))."</td><td>$out</td><td>$stat</td></tr>";
                    }
                    if ($res->num_rows === 0) echo "<tr><td colspan='4' style='text-align:center;'>No records found.</td></tr>";
                    $stmt->close();
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($is_admin): ?>
    <div class="search-section" style="position:relative;">
        <span style="position:absolute; left:20px; top:18px; opacity:0.4;">🔍</span>
        <input type="text" id="staffSearch" class="search-input" placeholder="Search staff records..." onkeyup="filterTable()">
    </div>

    <div class="recent-table">
        <table id="attendanceTable">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Staff ID</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Selfie</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $res = $conn->query("SELECT a.*, s.full_name, s.photo FROM attendance a JOIN staff s ON a.staff_id = s.staff_id ORDER BY a.id DESC LIMIT 50");
                    if ($res && !is_bool($res) && $res->num_rows > 0) {
                        while($row = $res->fetch_assoc()){
                        $status_badge = $row['clock_out'] ? 'badge-info' : 'badge-success';
                        $status_text = $row['clock_out'] ? 'Completed' : 'Working';
                        $selfie = $row['photo_in'] ?: $row['photo_out'];
                        
                        echo "<tr>";
                        echo "<td>";
                        if($row['photo']) echo "<img src='{$row['photo']}' class='staff-thumb'>";
                        echo "<strong>".htmlspecialchars($row['full_name'])."</strong></td>";
                        echo "<td><code>".htmlspecialchars($row['staff_id'])."</code></td>";
                        echo "<td>".date('M j, g:i A', strtotime($row['clock_in']))."</td>";
                        echo "<td>".($row['clock_out'] ? date('M j, g:i A', strtotime($row['clock_out'])) : '—')."</td>";
                        echo "<td>";
                        if($selfie) echo "<img src='{$selfie}' class='staff-thumb' style='border-radius:4px; cursor:pointer;' onclick='showFull(this.src)'>";
                        else echo "<span style='color:#ccc;'>No photo</span>";
                        echo "</td>";
                        echo "<td><span class='badge {$status_badge}'>{$status_text}</span></td>";
                        echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center; padding:40px; color:#94a3b8;'>No attendance records yet.</td></tr>";
                    }
                ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="footer">&copy; <?php echo date("Y"); ?> Attendance System | Solomon Mbewu</div>
  </div>

  <?php if ($staff_id): ?>
  <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.14/dist/face-api.js"></script>
  <script src="/asset/js/clock-face.js"></script>
  <script>
    const STAFF_PROFILE_PHOTO = <?php echo json_encode($staff_profile_photo); ?>;
  </script>
  <?php endif; ?>

  <script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const clockBtn = document.getElementById('clockBtn');
    let currentCoords = null;
    let faceReady = false;
    let geoReady = false;

    function updateClockButtonState() {
        if (!clockBtn) return;
        const ready = faceReady && geoReady;
        clockBtn.disabled = !ready;
    }

  <?php if ($staff_id): ?>
    (async function initStaffClocking() {
        const faceStatus = document.getElementById('faceStatus');
        try {
            await ClockFace.loadModels();
            await ClockFace.loadProfilePhoto(STAFF_PROFILE_PHOTO);
            faceReady = true;
            faceStatus.innerHTML = '✅ Face profile loaded — look at the camera to verify';
        } catch (e) {
            faceStatus.innerHTML = '❌ ' + e.message;
            if (clockBtn) clockBtn.disabled = true;
        }
        updateClockButtonState();
    })();

    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia && video) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } })
        .then(stream => { video.srcObject = stream; })
        .catch(() => {
            document.getElementById('camera-container').innerHTML =
                "<p style='color:white;padding:20px;text-align:center;'>Camera access denied. Allow camera permission and refresh.</p>";
        });
    }

    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(
            (pos) => {
                currentCoords = pos.coords;
                geoReady = true;
                document.getElementById('geoStatus').innerHTML =
                    '✅ Location ready (±' + Math.round(pos.coords.accuracy || 0) + 'm accuracy)';
                updateClockButtonState();
            },
            () => {
                document.getElementById('geoStatus').innerHTML = '❌ GPS denied — enable location for this site';
                geoReady = false;
                updateClockButtonState();
            },
            { enableHighAccuracy: true, maximumAge: 8000, timeout: 20000 }
        );
    }
  <?php endif; ?>

    function playBeep(freq, duration, type='sine') {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = type;
            osc.frequency.value = freq;
            gain.gain.value = 0.1;
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            setTimeout(() => { osc.stop(); ctx.close(); }, duration);
        } catch (e) { /* audio optional */ }
    }

    function showApiError(msg) {
        document.getElementById('scanningOverlay').style.display = 'none';
        document.getElementById('camera-container').style.borderColor = '#ef4444';
        document.getElementById('apiResult').style.color = '#ef4444';
        document.getElementById('apiResult').innerHTML = '❌ ' + msg;
    }

    async function processClocking(action) {
        if (!currentCoords) {
            alert('Waiting for GPS location…');
            return;
        }
        if (typeof ClockFace === 'undefined') {
            alert('Face verification is not available. Refresh the page.');
            return;
        }
        if (!ClockFace.isReady()) {
            alert('Face recognition is still loading. Please wait.');
            return;
        }

        playBeep(800, 200, 'square');
        document.getElementById('scanningOverlay').style.display = 'block';
        document.getElementById('camera-container').style.borderColor = '#3b82f6';

        clockBtn.disabled = true;
        const originalText = clockBtn.innerHTML;
        clockBtn.innerHTML = 'Verifying face…';

        let faceResult = { match: true, distance: 0 };
        try {
            faceResult = await ClockFace.verifyVideoFace(video);
        } catch (e) {
            showApiError(e.message || 'Face verification failed.');
            clockBtn.disabled = false;
            clockBtn.innerHTML = originalText;
            return;
        }

        if (!faceResult.match) {
            playBeep(300, 400, 'sawtooth');
            showApiError(faceResult.message);
            clockBtn.disabled = false;
            clockBtn.innerHTML = originalText;
            setTimeout(() => { document.getElementById('camera-container').style.borderColor = '#e2e8f0'; }, 2000);
            return;
        }

        const ctx = canvas.getContext('2d');
        canvas.width = 320;
        canvas.height = 240;
        ctx.drawImage(video, 0, 0, 320, 240);
        const photoData = canvas.toDataURL('image/jpeg', 0.55);

        clockBtn.innerHTML = 'Saving attendance…';

        const formData = new FormData();
        formData.append('action', action);
        formData.append('lat', currentCoords.latitude);
        formData.append('lng', currentCoords.longitude);
        formData.append('photo', photoData);
        formData.append('face_verified', '1');
        formData.append('face_distance', String(faceResult.distance));

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 45000);

        try {
            const res = await fetch('/api/web_clock.php', { method: 'POST', body: formData, signal: controller.signal });
            clearTimeout(timeoutId);
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                throw new Error('Server returned an invalid response. Try again.');
            }

            document.getElementById('scanningOverlay').style.display = 'none';
            if (data.status === 'success') {
                playBeep(1200, 400, 'sine');
                document.getElementById('camera-container').style.borderColor = '#10b981';
                document.getElementById('apiResult').style.color = '#10b981';
                document.getElementById('apiResult').innerHTML = '✅ VERIFIED! ' + data.message;
                clockBtn.innerHTML = 'Verified';
                setTimeout(() => location.reload(), 2000);
            } else {
                playBeep(300, 400, 'sawtooth');
                showApiError(data.message || 'Verification failed.');
                clockBtn.disabled = false;
                clockBtn.innerHTML = originalText;
                setTimeout(() => { document.getElementById('camera-container').style.borderColor = '#e2e8f0'; }, 2000);
            }
        } catch (err) {
            clearTimeout(timeoutId);
            const msg = err.name === 'AbortError'
                ? 'Request timed out. Check your connection and try again.'
                : (err.message || 'Network error. Please try again.');
            showApiError(msg);
            clockBtn.disabled = false;
            clockBtn.innerHTML = originalText;
        }
    }

    function filterTable() {
        const input = document.getElementById("staffSearch");
        const filter = input.value.toUpperCase();
        const tr = document.getElementById("attendanceTable").getElementsByTagName("tr");
        for (let i = 1; i < tr.length; i++) {
            let found = false;
            const tds = tr[i].getElementsByTagName("td");
            for (let j = 0; j < tds.length; j++) {
                if (tds[j].textContent.toUpperCase().indexOf(filter) > -1) { found = true; break; }
            }
            tr[i].style.display = found ? "" : "none";
        }
    }

    function showFull(src) { window.open(src, '_blank'); }
  </script>
</body>
</html>
<?php
} catch (Throwable $t) {
    echo "<div style='padding:40px; background:#fee2e2; color:#991b1b; font-family:sans-serif;'>";
    echo "<h2>Fatal Application Error</h2>";
    echo "<p><strong>Message:</strong> " . $t->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $t->getFile() . " on line " . $t->getLine() . "</p>";
    echo "<pre>" . $t->getTraceAsString() . "</pre>";
    echo "</div>";
}
?>
