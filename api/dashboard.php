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

// TEMPORARY: Enable errors if something is failing
if ($is_admin) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}
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
        width: 100%; max-width: 320px; margin: 0 auto 20px;
        border-radius: 16px; overflow: hidden; background: #000;
        aspect-ratio: 4/3; position: relative; border: 4px solid #f1f5f9;
    }
    #video { width: 100%; height: 100%; object-fit: cover; }
    #canvas { display: none; }

    .clock-btn {
        background: var(--primary); color: white; border: none;
        padding: 18px 50px; font-size: 18px; font-weight: 700;
        border-radius: 16px; cursor: pointer; transition: all 0.3s var(--ease);
        box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
    }
    .clock-btn.out { background: #ef4444; box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3); }

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
                <button id="clockBtn" class="clock-btn" onclick="processClocking('clock_in')">Verify & Clock In</button>
            <?php else: ?>
                <button id="clockBtn" class="clock-btn out" onclick="processClocking('clock_out')">Verify & Clock Out</button>
            <?php endif; ?>
        </div>
        
        <p id="geoStatus" style="margin-top:15px; color:var(--text-muted); font-size:14px;">📍 Detecting location...</p>
        <div id="apiResult" style="margin-top:15px; font-weight:600;"></div>
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

  <script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const clockBtn = document.getElementById('clockBtn');
    let currentCoords = null;

    // Initialize Camera
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
        .then(stream => { video.srcObject = stream; })
        .catch(err => { 
            console.error("Camera error:", err);
            document.getElementById('camera-container').innerHTML = "<p style='color:white; padding:20px;'>Camera access denied or not available.</p>";
        });
    }

    // Geolocation
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => { 
                currentCoords = pos.coords; 
                document.getElementById('geoStatus').innerHTML = "✅ Location Ready";
            },
            (err) => { 
                document.getElementById('geoStatus').innerHTML = "❌ GPS Access Denied";
                if(clockBtn) clockBtn.disabled = true;
            }
        );
    }

    function processClocking(action) {
        if (!currentCoords) { alert("Waiting for location..."); return; }
        
        // Capture Frame
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, 640, 480);
        const photoData = canvas.toDataURL('image/jpeg', 0.7);

        clockBtn.disabled = true;
        const originalText = clockBtn.innerHTML;
        clockBtn.innerHTML = "Verifying...";

        const formData = new FormData();
        formData.append('action', action);
        formData.append('lat', currentCoords.latitude);
        formData.append('lng', currentCoords.longitude);
        formData.append('photo', photoData);

        fetch('/api/web_clock.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('apiResult').style.color = "#10b981";
                document.getElementById('apiResult').innerHTML = data.message;
                setTimeout(() => location.reload(), 1500);
            } else {
                document.getElementById('apiResult').style.color = "#ef4444";
                document.getElementById('apiResult').innerHTML = data.message;
                clockBtn.disabled = false;
                clockBtn.innerHTML = originalText;
            }
        });
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
