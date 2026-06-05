<?php
/**
 * Register staff phone GPS as allowed clock-in zone (fixes branch coords vs real GPS).
 */
include(__DIR__ . "/includes/config.php");
include(__DIR__ . "/lib/Geolocation.php");

header('Content-Type: application/json');

if (empty($_SESSION['staff_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Staff login required.']);
    exit;
}

$staff_id = $_SESSION['staff_id'];
$lat      = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng      = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$sync_branch = ($_POST['sync_branch'] ?? '1') === '1';

if ($lat === null || $lng === null) {
    echo json_encode(['status' => 'error', 'message' => 'GPS coordinates missing.']);
    exit;
}

$radius = 300;

$stmt = $conn->prepare(
    "UPDATE staff SET clock_lat = ?, clock_lng = ?, clock_radius = ? WHERE staff_id = ?"
);
$stmt->bind_param("ddis", $lat, $lng, $radius, $staff_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'Could not save location.']);
    exit;
}

$branch_updated = null;

if ($sync_branch) {
    $stmt = $conn->prepare("SELECT branch FROM staff WHERE staff_id = ? LIMIT 1");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $branch_name = trim($row['branch'] ?? '');

    if ($branch_name !== '') {
        $upd = $conn->prepare(
            "UPDATE branches SET latitude = ?, longitude = ? WHERE LOWER(TRIM(branch_name)) = LOWER(TRIM(?))"
        );
        $upd->bind_param("dds", $lat, $lng, $branch_name);
        if ($upd->execute() && $upd->affected_rows > 0) {
            $branch_updated = $branch_name;
        }
        $upd->close();
    }

    if ($branch_updated === null) {
        $branches = [];
        $res = $conn->query("SELECT id, branch_name, latitude, longitude FROM branches");
        while ($b = $res->fetch_assoc()) {
            $branches[] = $b;
        }
        if (!empty($branches)) {
            $nearest = null;
            $nearestDist = PHP_FLOAT_MAX;
            foreach ($branches as $b) {
                $d = Geolocation::getDistance($lat, $lng, (float)$b['latitude'], (float)$b['longitude']);
                if ($d < $nearestDist) {
                    $nearestDist = $d;
                    $nearest = $b;
                }
            }
            if ($nearest) {
                $upd = $conn->prepare("UPDATE branches SET latitude = ?, longitude = ? WHERE id = ?");
                $upd->bind_param("ddi", $lat, $lng, $nearest['id']);
                if ($upd->execute()) {
                    $branch_updated = $nearest['branch_name'];
                }
                $upd->close();
            }
        }
    }
}

echo json_encode([
    'status'          => 'success',
    'message'         => 'Clock location saved! You can clock in within ' . $radius . 'm of this spot.',
    'lat'             => $lat,
    'lng'             => $lng,
    'radius'          => $radius,
    'branch_updated'  => $branch_updated,
]);
