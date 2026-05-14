<?php
include(__DIR__ . "/../includes/config.php");

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['staff_id'])) {
    echo "<script>window.location.href='/index.php';</script>";
    exit;
}

$is_admin = isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin');
$staff_id = $_SESSION['staff_id'] ?? null;
$display_name = $_SESSION['admin'] ?? 'User';
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
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.4);
    }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
    
    .dashboard-header {
        background: linear-gradient(135deg, #1e293b, #334155);
        color: white;
        padding: 40px;
        border-radius: 24px;
        margin-bottom: 30px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }
    .dashboard-header::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.03);
        border-radius: 50%;
    }
    .dashboard-header h2 { font-size: 32px; font-weight: 800; margin-bottom: 8px; }
    .dashboard-header p { opacity: 0.8; font-size: 16px; }

    .clocking-card {
        background: white;
        padding: 30px;
        border-radius: 24px;
        margin-bottom: 30px;
        text-align: center;
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        transition: all 0.3s var(--ease);
    }
    .clocking-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .clocking-card h3 { font-size: 20px; font-weight: 700; margin-bottom: 20px; color: var(--text); }
    
    .clock-btn {
        background: var(--primary);
        color: white;
        border: none;
        padding: 18px 50px;
        font-size: 18px;
        font-weight: 700;
        border-radius: 16px;
        cursor: pointer;
        transition: all 0.3s var(--ease);
        box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
    }
    .clock-btn:hover { background: var(--primary-light); transform: scale(1.02); }
    .clock-btn:disabled { background: #cbd5e1; box-shadow: none; cursor: not-allowed; }
    .clock-btn.out { background: #ef4444; box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3); }

    .search-section {
        margin-bottom: 30px;
        position: relative;
    }
    .search-input {
        width: 100%;
        padding: 16px 24px 16px 50px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 16px;
        font-size: 16px;
        font-family: inherit;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s var(--ease);
    }
    .search-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
    .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); opacity: 0.4; }

    .card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }
    .stat-card {
        background: white;
        padding: 24px;
        border-radius: 20px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
    }
    .stat-card h4 { color: var(--text-muted); font-size: 14px; font-weight: 600; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-card .val { font-size: 28px; font-weight: 800; color: var(--text); }

    .recent-table {
        background: white;
        border-radius: 24px;
        padding: 10px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 16px 20px; font-size: 13px; color: var(--text-muted); font-weight: 700; border-bottom: 1px solid var(--border); }
    td { padding: 16px 20px; font-size: 14px; color: var(--text); border-bottom: 1px solid #f1f5f9; }
    tr:last-child td { border-bottom: none; }
    .badge { padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 12px; }
  </style>
</head>
<body>

  <?php include(__DIR__ . "/../includes/sidebar.php"); ?>

  <div class="content">
    <div class="dashboard-header">
        <h2>Welcome, <?php echo htmlspecialchars($display_name); ?> 👋</h2>
        <p>System status: All systems operational. Have a productive day!</p>
    </div>

    <?php if ($staff_id): ?>
    <div class="clocking-card">
        <h3>🕒 Web Clocking (Geofenced)</h3>
        <?php
            // IMPROVED SHIFT LOGIC: Find latest open session (any date)
            $stmt = $conn->prepare("SELECT id FROM attendance WHERE staff_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
            $stmt->bind_param("s", $staff_id);
            $stmt->execute();
            $open_record = $stmt->get_result()->fetch_assoc();
            $is_clocked_in = !empty($open_record);
            $stmt->close();
        ?>
        
        <div id="clockControls">
            <?php if (!$is_clocked_in): ?>
                <button id="clockBtn" class="clock-btn" onclick="processClocking('clock_in')">Clock In Now</button>
            <?php else: ?>
                <button id="clockBtn" class="clock-btn out" onclick="processClocking('clock_out')">Clock Out Now</button>
            <?php endif; ?>
        </div>
        
        <p id="geoStatus" style="margin-top:15px; color:var(--text-muted); font-size:14px;">📍 Detecting location...</p>
        <div id="apiResult" style="margin-top:15px; font-weight:600;"></div>
    </div>
    <?php endif; ?>

    <?php if ($is_admin): ?>
    <div class="card-grid">
        <div class="stat-card">
            <h4>Total Employees</h4>
            <?php
              $res = $conn->query("SELECT COUNT(*) AS t FROM staff");
              $row = $res->fetch_assoc();
              echo "<div class='val'>{$row['t']}</div>";
            ?>
        </div>
        <div class="stat-card">
            <h4>Present Today</h4>
            <?php
              $today = date('Y-m-d');
              $res = $conn->query("SELECT COUNT(DISTINCT staff_id) AS t FROM attendance WHERE DATE(clock_in) = '$today'");
              $row = $res->fetch_assoc();
              echo "<div class='val'>{$row['t']}</div>";
            ?>
        </div>
        <div class="stat-card">
            <h4>Currently Clocked In</h4>
            <?php
              $res = $conn->query("SELECT COUNT(*) AS t FROM attendance WHERE clock_out IS NULL");
              $row = $res->fetch_assoc();
              echo "<div class='val'>{$row['t']}</div>";
            ?>
        </div>
    </div>

    <div class="search-section">
        <span class="search-icon">🔍</span>
        <input type="text" id="staffSearch" class="search-input" placeholder="Search by name, staff ID, or phone..." onkeyup="filterTable()">
    </div>

    <div class="recent-table">
        <table id="attendanceTable">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Staff ID</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $res = $conn->query("SELECT a.*, s.full_name FROM attendance a JOIN staff s ON a.staff_id = s.staff_id ORDER BY a.id DESC LIMIT 50");
                    while($row = $res->fetch_assoc()){
                        $status_badge = $row['clock_out'] ? 'badge-info' : 'badge-success';
                        $status_text = $row['clock_out'] ? 'Completed' : 'Working';
                        echo "<tr>";
                        echo "<td><strong>".htmlspecialchars($row['full_name'])."</strong></td>";
                        echo "<td><code>".htmlspecialchars($row['staff_id'])."</code></td>";
                        echo "<td>".date('M j, g:i A', strtotime($row['clock_in']))."</td>";
                        echo "<td>".($row['clock_out'] ? date('M j, g:i A', strtotime($row['clock_out'])) : '—')."</td>";
                        echo "<td><span class='badge {$status_badge}'>{$status_text}</span></td>";
                        echo "</tr>";
                    }
                ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="footer">
      &copy; <?php echo date("Y"); ?> Attendance System | Premium Enterprise Edition
    </div>
  </div>

  <script>
    function filterTable() {
        const input = document.getElementById("staffSearch");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("attendanceTable");
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            let found = false;
            const tds = tr[i].getElementsByTagName("td");
            for (let j = 0; j < tds.length; j++) {
                if (tds[j].textContent.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
            tr[i].style.display = found ? "" : "none";
        }
    }

    let currentCoords = null;
    const geoStatus = document.getElementById('geoStatus');
    const clockBtn = document.getElementById('clockBtn');
    const apiResult = document.getElementById('apiResult');

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                currentCoords = pos.coords;
                geoStatus.innerHTML = `✅ Location Ready (${pos.coords.latitude.toFixed(4)}, ${pos.coords.longitude.toFixed(4)})`;
            },
            (err) => {
                geoStatus.innerHTML = "❌ Please enable Location Access to clock in/out.";
                if (clockBtn) clockBtn.disabled = true;
            }
        );
    }

    function processClocking(action) {
        if (!currentCoords) {
            alert("Waiting for GPS location...");
            return;
        }

        clockBtn.disabled = true;
        const originalText = clockBtn.innerHTML;
        clockBtn.innerHTML = "Processing...";

        const formData = new FormData();
        formData.append('action', action);
        formData.append('lat', currentCoords.latitude);
        formData.append('lng', currentCoords.longitude);

        fetch('/api/web_clock.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                apiResult.style.color = "#10b981";
                apiResult.innerHTML = data.message;
                setTimeout(() => location.reload(), 2000);
            } else {
                apiResult.style.color = "#ef4444";
                apiResult.innerHTML = "Error: " + data.message;
                clockBtn.disabled = false;
                clockBtn.innerHTML = originalText;
            }
        })
        .catch(err => {
            apiResult.innerHTML = "Network error. Try again.";
            clockBtn.disabled = false;
        });
    }
  </script>

</body>
</html>
