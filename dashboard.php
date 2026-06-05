<?php
session_start();
include("includes/config.php");

// Redirect to login if not logged in
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}

$staff_id = $_SESSION['staff_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Attendance System</title>
  <link rel="stylesheet" href="asset/css/style.css">
  <style>
    .clocking-card {
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: white;
      padding: 30px;
      border-radius: var(--radius-lg);
      margin-bottom: 30px;
      text-align: center;
      box-shadow: var(--shadow-lg);
    }
    .clocking-card h3 { margin-bottom: 15px; color: white; opacity: 0.9; }
    .clock-btn {
      background: white;
      color: var(--primary);
      border: none;
      padding: 15px 40px;
      font-size: 18px;
      font-weight: 700;
      border-radius: 50px;
      cursor: pointer;
      transition: all 0.3s var(--ease);
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .clock-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.2);
      background: #f8fafc;
    }
    .clock-btn:disabled {
      background: #cbd5e1;
      color: #94a3b8;
      cursor: not-allowed;
      transform: none;
    }
    .status-msg { margin-top: 15px; font-size: 14px; font-weight: 500; }
    #geoStatus { color: rgba(255,255,255,0.8); font-size: 13px; margin-top: 10px; }
  </style>
</head>
<body>

  <?php include("includes/sidebar.php"); ?>

  <div class="content">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h2>Welcome, <?php echo ucfirst(htmlspecialchars($_SESSION['admin'])); ?> 👋</h2>
            <p class="subtitle">Here's a quick overview of your attendance system.</p>
        </div>
        <?php if ($staff_id): ?>
            <div class="badge badge-info">Staff ID: <?php echo $staff_id; ?></div>
        <?php endif; ?>
    </div>

    <!-- ====== GEOFENCED WEB CLOCKING ====== -->
    <?php if ($staff_id): ?>
    <div class="clocking-card">
        <h3>🕒 Web Clocking (Face Verified + Geofenced)</h3>
        <?php
            // Check if clocked in today
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT id FROM attendance WHERE staff_id = ? AND DATE(clock_in) = ? AND clock_out IS NULL");
            $stmt->bind_param("ss", $staff_id, $today);
            $stmt->execute();
            $is_clocked_in = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        ?>
        
        <div id="clockControls">
            <?php if (!$is_clocked_in): ?>
                <button id="clockBtn" class="clock-btn" onclick="startFaceVerification('clock_in')">🔒 Clock In Now</button>
            <?php else: ?>
                <button id="clockBtn" class="clock-btn" style="background:var(--danger-bg); color:var(--danger);" onclick="startFaceVerification('clock_out')">🔒 Clock Out Now</button>
            <?php endif; ?>
        </div>
        
        <p id="geoStatus">📍 Checking location...</p>
        <div id="apiResult" class="status-msg"></div>
    </div>
    <?php endif; ?>

    <!-- ====== FACE VERIFICATION MODAL ====== -->
    <div id="faceModal" class="face-modal-overlay">
      <div class="face-modal">
        <div class="face-modal-header">
          <h3>🔐 Face Verification</h3>
          <button class="face-modal-close" onclick="closeFaceModal()">✕</button>
        </div>
        <div class="face-modal-body">
          <!-- Camera feed -->
          <div class="camera-container" id="cameraContainer">
            <video id="faceVideo" autoplay playsinline muted></video>
            <div class="face-guide" id="faceGuide"></div>
            <div class="scan-line" id="scanLine"></div>
          </div>

          <!-- Status -->
          <div class="face-status loading" id="faceStatus">
            ⏳ Initializing camera...
          </div>

          <!-- Comparison display (hidden until comparison done) -->
          <div id="faceCompareSection" style="display:none;">
            <div class="face-compare">
              <div style="text-align:center;">
                <img id="capturedFaceImg" class="face-compare-img" src="" alt="Your face">
                <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Live Capture</div>
              </div>
              <div class="face-compare-arrow">⟷</div>
              <div style="text-align:center;">
                <img id="profileFaceImg" class="face-compare-img" src="" alt="Profile photo">
                <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Profile Photo</div>
              </div>
            </div>
            <div class="confidence-bar">
              <div class="confidence-fill" id="confidenceFill" style="width:0%"></div>
            </div>
            <div class="face-compare-result" id="compareResult"></div>
          </div>

          <!-- Actions -->
          <div class="face-modal-actions" id="faceActions">
            <button class="btn-capture" id="captureBtn" onclick="captureAndVerify()" disabled>
              📸 Verify My Face
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="cards">

      <!-- Total Employees -->
      <div class="card">
        <h3>👥 Total Employees</h3>
        <?php
          $result = $conn->query("SELECT COUNT(*) AS total FROM staff");
          $row = $result->fetch_assoc();
          echo "<p>{$row['total']}</p>";
        ?>
      </div>

      <!-- Attendance Records -->
      <div class="card">
        <h3>🕒 Total Attendance</h3>
        <?php
          $result = $conn->query("SELECT COUNT(*) AS total FROM attendance");
          $row = $result->fetch_assoc();
          echo "<p>{$row['total']}</p>";
        ?>
      </div>

      <!-- Today's Clock-ins -->
      <div class="card">
        <h3>📅 Today's Clock-ins</h3>
        <?php
          $today = date('Y-m-d');
          $result = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE DATE(clock_in) = '$today'");
          $row = $result->fetch_assoc();
          echo "<p>{$row['total']}</p>";
        ?>
      </div>

      <!-- Go to Reports -->
      <div class="card">
        <h3>📊 Reports</h3>
        <p><a href="reports.php" class="reports-link">View Reports →</a></p>
      </div>

    </div>

    <div class="footer">
      &copy; <?php echo date("Y"); ?> Attendance System | Powered by Solomon Collins
    </div>
  </div>

  <!-- face-api.js loaded ONLY when needed (lazy load) -->
  <!-- Unified Auth Module -->
  <script src="asset/js/face-api.min.js"></script>
  <script src="asset/js/auth.js"></script>
  <script>
    // Configure AuthManager for Dashboard
    AuthManager.video = document.getElementById('faceVideo');
    AuthManager.guide = document.getElementById('faceGuide');
    AuthManager.statusEl = document.getElementById('faceStatus');
    AuthManager.scanLine = document.getElementById('scanLine');

    let pendingAction = null;
    const geoStatus = document.getElementById('geoStatus');
    const clockBtn = document.getElementById('clockBtn');
    const apiResult = document.getElementById('apiResult');

    // Run Geolocation on load
    AuthManager.initGeolocation()
        .then(pos => {
            geoStatus.innerHTML = `✅ Location Ready: ${pos.latitude.toFixed(4)}, ${pos.longitude.toFixed(4)}`;
        })
        .catch(err => {
            geoStatus.innerHTML = "❌ " + err;
            if (clockBtn) clockBtn.disabled = true;
        });

    function startFaceVerification(action) {
        if (!AuthManager.currentCoords) {
            alert("Location not ready. Please wait or enable GPS.");
            return;
        }

        pendingAction = action;

        // Show modal
        document.getElementById('faceModal').classList.add('active');
        document.getElementById('faceCompareSection').style.display = 'none';
        document.getElementById('faceGuide').className = 'face-guide';
        document.getElementById('scanLine').classList.remove('active');
        
        const actionsDiv = document.getElementById('faceActions');
        actionsDiv.innerHTML = '<button class="btn-capture" id="captureBtn" onclick="captureAndVerify()" disabled>📸 Verify My Face</button>';

        AuthManager.setStatus('loading', '📷 Starting camera...');
        
        Promise.all([
            AuthManager.loadFaceModels(),
            AuthManager.startCamera()
        ]).then(() => {
            AuthManager.setStatus('scanning', '🔍 Position your face and click "Verify"');
            document.getElementById('faceGuide').classList.add('scanning');
            document.getElementById('scanLine').classList.add('active');
            document.getElementById('captureBtn').disabled = false;
        }).catch(err => {
            AuthManager.setStatus('error', '❌ ' + err.message);
        });
    }

    async function captureAndVerify() {
        const btn = document.getElementById('captureBtn');
        btn.disabled = true;
        btn.innerHTML = '⏳ Analyzing...';

        try {
            // First get profile photo URL
            const res = await fetch('api/verify_face.php');
            const data = await res.json();
            if (data.status !== 'success') throw new Error(data.message);

            const result = await AuthManager.verifyFace(data.photo_url);

            if (result.isMatch) {
                AuthManager.setStatus('matched', `✅ Face Verified (${result.confidence}%)`);
                btn.innerHTML = '✅ Confirmed';
                setTimeout(() => {
                    closeFaceModal();
                    processClocking(pendingAction);
                }, 1000);
            } else {
                AuthManager.setStatus('rejected', `❌ Face mismatch (${result.confidence}%)`);
                btn.disabled = false;
                btn.innerHTML = 'Retry Verification';
            }
        } catch (err) {
            AuthManager.setStatus('error', '❌ ' + err.message);
            btn.disabled = false;
            btn.innerHTML = 'Retry Verification';
        }
    }

    function closeFaceModal() {
        document.getElementById('faceModal').classList.remove('active');
        AuthManager.stopCamera();
    }

    function processClocking(action) {
        clockBtn.disabled = true;
        clockBtn.innerHTML = "Processing...";
        apiResult.innerHTML = "";

        const formData = new FormData();
        formData.append('action', action);
        formData.append('lat', AuthManager.currentCoords.latitude);
        formData.append('lng', AuthManager.currentCoords.longitude);

        fetch('api/web_clock.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                apiResult.style.color = "var(--success)";
                apiResult.innerHTML = data.message;
                setTimeout(() => location.reload(), 1500);
            } else {
                apiResult.style.color = "var(--danger)";
                apiResult.innerHTML = "❌ " + data.message;
                clockBtn.disabled = false;
                clockBtn.innerHTML = action === 'clock_in' ? '🔒 Clock In Now' : '🔒 Clock Out Now';
            }
        })
        .catch(() => {
            apiResult.innerHTML = "❌ System error.";
            clockBtn.disabled = false;
        });
    }
  </script>

</body>
</html>
