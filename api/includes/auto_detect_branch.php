<?php
/**
 * Auto-detect the branch based on device GPS coordinates.
 * Strictly verifies whether the device is within the registered radius of ANY active branch.
 * If employee is inside the meter radius of a branch, that branch name is automatically detected and assigned.
 * If outside all registered branches, the punch is immediately blocked.
 */
if (!function_exists('auto_detect_gps_branch')) {
    function auto_detect_gps_branch(mysqli $conn, ?float $lat, ?float $lng, ?string $staff_id = null): array {
        // 1. Fetch all registered company branches
        $branches = [];
        $res = $conn->query("SELECT id, branch_name, latitude, longitude, radius_meters FROM branches WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $branches[] = [
                    'id'            => (int)$row['id'],
                    'branch_name'   => trim((string)$row['branch_name']),
                    'latitude'      => (float)$row['latitude'],
                    'longitude'     => (float)$row['longitude'],
                    'radius_meters' => !empty($row['radius_meters']) ? (int)$row['radius_meters'] : 200
                ];
            }
        }

        // 2. Also check if this employee has an individual custom GPS override
        if (!empty($staff_id)) {
            $stmtStaff = $conn->prepare("SELECT clock_lat, clock_lng, clock_radius, branch FROM staff WHERE staff_id = ? LIMIT 1");
            if ($stmtStaff) {
                $stmtStaff->bind_param("s", $staff_id);
                $stmtStaff->execute();
                $sRow = $stmtStaff->get_result()->fetch_assoc();
                $stmtStaff->close();
                if ($sRow && !empty($sRow['clock_lat']) && !empty($sRow['clock_lng'])) {
                    $branches[] = [
                        'id'            => 0,
                        'branch_name'   => !empty($sRow['branch']) ? trim($sRow['branch']) : 'Assigned Location',
                        'latitude'      => (float)$sRow['clock_lat'],
                        'longitude'     => (float)$sRow['clock_lng'],
                        'radius_meters' => !empty($sRow['clock_radius']) ? (int)$sRow['clock_radius'] : 300
                    ];
                }
            }
        }

        // 3. If NO branches are configured in the system at all, allow as 'Main Office'
        if (empty($branches)) {
            return [
                'success'     => true,
                'branch_name' => 'Main Office',
                'distance'    => 0,
                'radius'      => 0,
                'error'       => null
            ];
        }

        // 4. If branches ARE configured, GPS is MANDATORY!
        if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng) || ($lat == 0.0 && $lng == 0.0)) {
            return [
                'success'     => false,
                'branch_name' => 'Unknown',
                'distance'    => 0,
                'radius'      => 0,
                'error'       => "📍 GPS Location Required: Please enable GPS / Location Services on your device and allow browser location access to clock in/out."
            ];
        }

        // 5. Haversine distance calculation to ALL branches
        $earthRadius = 6371000; // in meters
        $latFrom = deg2rad($lat);
        $lonFrom = deg2rad($lng);

        $matchedBranch = null;
        $closestBranch = null;
        $minDistance   = -1;

        foreach ($branches as $b) {
            $latTo = deg2rad($b['latitude']);
            $lonTo = deg2rad($b['longitude']);
            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
            $dist  = $angle * $earthRadius;

            if ($minDistance === -1 || $dist < $minDistance) {
                $minDistance   = $dist;
                $closestBranch = [
                    'branch_name' => $b['branch_name'],
                    'distance'    => $dist,
                    'radius'      => $b['radius_meters']
                ];
            }

            // Check if inside this branch's radius
            if ($dist <= $b['radius_meters']) {
                if ($matchedBranch === null || $dist < $matchedBranch['distance']) {
                    $matchedBranch = [
                        'branch_name' => $b['branch_name'],
                        'distance'    => $dist,
                        'radius'      => $b['radius_meters']
                    ];
                }
            }
        }

        // 6. Verification & Decision
        if ($matchedBranch !== null) {
            return [
                'success'     => true,
                'branch_name' => $matchedBranch['branch_name'],
                'distance'    => round($matchedBranch['distance'], 1),
                'radius'      => $matchedBranch['radius'],
                'error'       => null
            ];
        }

        // OUT OF RANGE: Block completely!
        $cName = $closestBranch ? $closestBranch['branch_name'] : 'Registered Branch';
        $cDist = $closestBranch ? round($closestBranch['distance']) : 0;
        $cRad  = $closestBranch ? $closestBranch['radius'] : 200;

        return [
            'success'     => false,
            'branch_name' => $cName,
            'distance'    => $cDist,
            'radius'      => $cRad,
            'error'       => "📍 Geofence Rejection: You are {$cDist}m away from {$cName}. Clocking is strictly restricted to within {$cRad}m of an authorized branch."
        ];
    }
}
