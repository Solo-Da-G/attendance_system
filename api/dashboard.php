<?php
session_start();
include(__DIR__ . "/../includes/config.php");

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

  <?php include(__DIR__ . "/../includes/sidebar.php"); ?>

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
        <h3>🕒 Web Clocking (Geofenced)</h3>
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
                <button id="clockBtn" class="clock-btn" onclick="processClocking('clock_in')">Clock In Now</button>
            <?php else: ?>
                <button id="clockBtn" class="clock-btn" style="background:var(--danger-bg); color:var(--danger);" onclick="processClocking('clock_out')">Clock Out Now</button>
            <?php endif; ?>
        </div>
        
        <p id="geoStatus">📍 Checking location...</p>
        <div id="apiResult" class="status-msg"></div>
    </div>
    <?php endif; ?>

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
    let currentCoords = null;
    const geoStatus = document.getElementById('geoStatus');
    const clockBtn = document.getElementById('clockBtn');
    const apiResult = document.getElementById('apiResult');

    // Get Location on Load
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
                clockBtn.innerHTML = action === 'clock_in' ? 'Clock In Now' : 'Clock Out Now';
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
