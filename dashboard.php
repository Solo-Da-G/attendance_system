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

  <script>
    // ============================================================
    // GLOBAL STATE
    // ============================================================
    let currentCoords = null;
    let pendingAction = null;
    let faceModelsLoaded = false;
    let videoStream = null;
    let profilePhotoUrl = null;
    let profileDescriptor = null;

    const geoStatus = document.getElementById('geoStatus');
    const clockBtn = document.getElementById('clockBtn');
    const apiResult = document.getElementById('apiResult');

    // ============================================================
    // GEOLOCATION (runs on page load — lightweight, no delay)
    // ============================================================
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                currentCoords = pos.coords;
                geoStatus.innerHTML = `✅ Location Ready: ${pos.coords.latitude.toFixed(4)}, ${pos.coords.longitude.toFixed(4)}`;
            },
            (err) => {
                geoStatus.innerHTML = "❌ Error: Please enable Location Access to clock in/out.";
                if (clockBtn) clockBtn.disabled = true;
            }
        );
    } else {
        geoStatus.innerHTML = "❌ Geolocation is not supported by this browser.";
        if (clockBtn) clockBtn.disabled = true;
    }

    // ============================================================
    // PRELOAD PROFILE PHOTO (background, after page loads)
    // ============================================================
    window.addEventListener('load', () => {
        fetch('api/verify_face.php')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    profilePhotoUrl = data.photo_url;
                    // Preload the image
                    const img = new Image();
                    img.src = profilePhotoUrl;
                }
            })
            .catch(() => {}); // Silent fail — will re-check when modal opens
    });

    // ============================================================
    // FACE VERIFICATION FLOW
    // ============================================================
    function startFaceVerification(action) {
        if (!currentCoords) {
            alert("Location not ready. Please wait or enable GPS.");
            return;
        }

        pendingAction = action;

        // Show modal
        document.getElementById('faceModal').classList.add('active');
        document.getElementById('faceCompareSection').style.display = 'none';
        document.getElementById('faceGuide').className = 'face-guide';
        document.getElementById('scanLine').classList.remove('active');
        
        const captureBtn = document.getElementById('captureBtn');
        captureBtn.disabled = true;
        captureBtn.innerHTML = '📸 Verify My Face';

        const actionsDiv = document.getElementById('faceActions');
        actionsDiv.innerHTML = '<button class="btn-capture" id="captureBtn" onclick="captureAndVerify()" disabled>📸 Verify My Face</button>';

        // Step 1: Check profile photo
        setFaceStatus('loading', '⏳ Checking your profile photo...');

        fetch('api/verify_face.php')
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'success') {
                    setFaceStatus('error', '⚠️ ' + data.message);
                    return;
                }

                profilePhotoUrl = data.photo_url;
                document.getElementById('profileFaceImg').src = profilePhotoUrl;

                // Step 2: Start camera
                setFaceStatus('loading', '📷 Starting camera...');
                startCamera();
            })
            .catch(err => {
                setFaceStatus('error', '❌ Failed to check profile. Please try again.');
            });
    }

    async function startCamera() {
        try {
            videoStream = await navigator.mediaDevices.getUserMedia({
                video: { 
                    width: { ideal: 640 }, 
                    height: { ideal: 480 },
                    facingMode: 'user'
                }
            });

            const video = document.getElementById('faceVideo');
            video.srcObject = videoStream;
            
            video.onloadedmetadata = async () => {
                video.play();
                
                // Step 3: Load face-api models (lazy load)
                await loadFaceModels();
                
                if (faceModelsLoaded) {
                    setFaceStatus('scanning', '🔍 Position your face in the circle and click "Verify"');
                    document.getElementById('faceGuide').classList.add('scanning');
                    document.getElementById('scanLine').classList.add('active');
                    document.getElementById('captureBtn').disabled = false;

                    // Start live face detection indicator
                    startLiveFaceDetection();
                }
            };
        } catch (err) {
            console.error('Camera error:', err);
            setFaceStatus('error', '❌ Camera access denied. Please allow camera permission and try again.');
        }
    }

    async function loadFaceModels() {
        if (faceModelsLoaded) return;

        setFaceStatus('loading', '🧠 Loading AI face recognition models...');

        try {
            // Lazy load face-api.js
            if (typeof faceapi === 'undefined') {
                await loadScript('asset/js/face-api.min.js');
            }

            // Load models from local directory
            const MODEL_URL = 'asset/models';
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
            ]);

            faceModelsLoaded = true;
            console.log('Face-api models loaded successfully');
        } catch (err) {
            console.error('Model loading error:', err);
            setFaceStatus('error', '❌ Failed to load face recognition models. Please refresh and try again.');
        }
    }

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    // Live face detection overlay (shows green when face detected)
    let liveDetectionInterval = null;

    function startLiveFaceDetection() {
        const video = document.getElementById('faceVideo');
        const guide = document.getElementById('faceGuide');

        liveDetectionInterval = setInterval(async () => {
            try {
                const detection = await faceapi.detectSingleFace(
                    video, 
                    new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 })
                );
                
                if (detection) {
                    guide.classList.add('detected');
                    guide.classList.remove('scanning');
                } else {
                    guide.classList.remove('detected');
                    guide.classList.add('scanning');
                }
            } catch (e) {
                // Ignore detection errors during live feed
            }
        }, 500);
    }

    function stopLiveFaceDetection() {
        if (liveDetectionInterval) {
            clearInterval(liveDetectionInterval);
            liveDetectionInterval = null;
        }
    }

    // ============================================================
    // CAPTURE AND VERIFY
    // ============================================================
    async function captureAndVerify() {
        const video = document.getElementById('faceVideo');
        const captureBtn = document.getElementById('captureBtn');
        
        captureBtn.disabled = true;
        captureBtn.innerHTML = '⏳ Analyzing face...';
        stopLiveFaceDetection();

        setFaceStatus('scanning', '🔍 Analyzing your face...');
        document.getElementById('scanLine').classList.add('active');

        try {
            // Step 1: Detect face in webcam
            const webcamDetection = await faceapi
                .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!webcamDetection) {
                setFaceStatus('rejected', '❌ No face detected. Please position your face clearly in the circle.');
                showRetryButton();
                return;
            }

            // Capture webcam frame as image for comparison display
            const captureCanvas = document.createElement('canvas');
            captureCanvas.width = video.videoWidth;
            captureCanvas.height = video.videoHeight;
            captureCanvas.getContext('2d').drawImage(video, 0, 0);
            document.getElementById('capturedFaceImg').src = captureCanvas.toDataURL('image/jpeg');

            setFaceStatus('scanning', '🔍 Comparing with your profile photo...');

            // Step 2: Load and analyze profile photo
            const profileImg = await faceapi.fetchImage(profilePhotoUrl);
            const profileDetection = await faceapi
                .detectSingleFace(profileImg, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.3 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!profileDetection) {
                setFaceStatus('error', '⚠️ Cannot detect face in your profile photo. Please ask admin to upload a clear front-facing photo.');
                showRetryButton();
                return;
            }

            // Step 3: Compare face descriptors
            const distance = faceapi.euclideanDistance(webcamDetection.descriptor, profileDetection.descriptor);
            const threshold = 0.5;
            const isMatch = distance < threshold;
            
            // Calculate confidence percentage (inverse of distance, capped)
            const confidence = Math.max(0, Math.min(100, Math.round((1 - distance) * 100)));

            // Show comparison UI
            document.getElementById('faceCompareSection').style.display = 'block';
            document.getElementById('scanLine').classList.remove('active');
            
            const confidenceFill = document.getElementById('confidenceFill');
            confidenceFill.style.width = confidence + '%';
            confidenceFill.className = 'confidence-fill ' + (isMatch ? 'high' : 'low');

            const capturedImg = document.getElementById('capturedFaceImg');
            const profileImgEl = document.getElementById('profileFaceImg');
            capturedImg.classList.toggle('match', isMatch);
            capturedImg.classList.toggle('no-match', !isMatch);
            profileImgEl.classList.toggle('match', isMatch);
            profileImgEl.classList.toggle('no-match', !isMatch);

            const guide = document.getElementById('faceGuide');
            guide.classList.remove('scanning', 'detected');
            guide.classList.add(isMatch ? 'detected' : 'no-match');

            if (isMatch) {
                // ✅ FACE MATCHED — proceed with clock
                setFaceStatus('matched', `✅ Face verified! Confidence: ${confidence}%`);
                document.getElementById('compareResult').innerHTML = 
                    `<strong style="color: var(--success);">Identity Confirmed</strong> — Match score: ${confidence}%`;

                // Auto-proceed with clocking after short delay
                const actionsDiv = document.getElementById('faceActions');
                actionsDiv.innerHTML = '<button class="btn-capture" style="background:linear-gradient(135deg, var(--success), #059669) !important;" disabled>✅ Verified — Processing clock...</button>';

                setTimeout(() => {
                    closeFaceModal();
                    processClocking(pendingAction);
                }, 1500);

            } else {
                // ❌ FACE DOES NOT MATCH
                setFaceStatus('rejected', `❌ Face does not match! Confidence: ${confidence}% (Required: 50%+)`);
                document.getElementById('compareResult').innerHTML = 
                    `<strong style="color: var(--danger);">Identity NOT Confirmed</strong> — The face captured does not match your profile photo.`;

                showRetryButton();
            }

        } catch (err) {
            console.error('Face verification error:', err);
            setFaceStatus('error', '❌ Face verification failed. Please try again.');
            showRetryButton();
        }
    }

    function showRetryButton() {
        const actionsDiv = document.getElementById('faceActions');
        actionsDiv.innerHTML = `
            <button class="btn-retry" onclick="retryVerification()">🔄 Try Again</button>
            <button class="btn-retry" onclick="closeFaceModal()" style="flex:0.5;">Cancel</button>
        `;
    }

    function retryVerification() {
        document.getElementById('faceCompareSection').style.display = 'none';
        document.getElementById('faceGuide').className = 'face-guide scanning';
        document.getElementById('scanLine').classList.add('active');
        
        const actionsDiv = document.getElementById('faceActions');
        actionsDiv.innerHTML = '<button class="btn-capture" id="captureBtn" onclick="captureAndVerify()">📸 Verify My Face</button>';
        
        setFaceStatus('scanning', '🔍 Position your face in the circle and click "Verify"');
        startLiveFaceDetection();
    }

    // ============================================================
    // MODAL CONTROLS
    // ============================================================
    function closeFaceModal() {
        document.getElementById('faceModal').classList.remove('active');
        stopLiveFaceDetection();

        // Stop camera
        if (videoStream) {
            videoStream.getTracks().forEach(track => track.stop());
            videoStream = null;
        }
        const video = document.getElementById('faceVideo');
        if (video) video.srcObject = null;
    }

    function setFaceStatus(type, message) {
        const el = document.getElementById('faceStatus');
        el.className = 'face-status ' + type;
        el.innerHTML = message;
    }

    // ============================================================
    // PROCESS CLOCKING (after face verification passes)
    // ============================================================
    function processClocking(action) {
        if (!currentCoords) {
            alert("Location not ready. Please wait or enable GPS.");
            return;
        }

        clockBtn.disabled = true;
        clockBtn.innerHTML = "Processing...";
        apiResult.innerHTML = "";

        const formData = new FormData();
        formData.append('action', action);
        formData.append('lat', currentCoords.latitude);
        formData.append('lng', currentCoords.longitude);

        fetch('api/web_clock.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                apiResult.style.color = "#86efac";
                apiResult.innerHTML = data.message;
                setTimeout(() => location.reload(), 1500);
            } else {
                apiResult.style.color = "#fca5a5";
                apiResult.innerHTML = "❌ " + data.message;
                if (data.debug) {
                    console.log("Distance check:", data.debug);
                }
                clockBtn.disabled = false;
                clockBtn.innerHTML = action === 'clock_in' ? '🔒 Clock In Now' : '🔒 Clock Out Now';
            }
        })
        .catch(err => {
            console.error(err);
            apiResult.innerHTML = "❌ Something went wrong.";
            clockBtn.disabled = false;
        });
    }
  </script>
</body>
</html>
