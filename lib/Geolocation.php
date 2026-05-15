<?php
/**
 * Geolocation Helper Class
 */
class Geolocation {
    public static function getDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public static function isWithinRadius($lat1, $lon1, $lat2, $lon2, $radiusMeters) {
        return self::getDistance($lat1, $lon1, $lat2, $lon2) <= $radiusMeters;
    }

    /**
     * @param float $accuracyBuffer Extra meters from GPS inaccuracy (capped)
     */
    public static function validateAgainstBranches($lat, $lng, array $branches, $accuracyBuffer = 0) {
        if (empty($branches)) {
            return ['allowed' => true, 'message' => null];
        }

        $buffer = min(max(0, (int)$accuracyBuffer), 200);

        $nearestDist = null;
        $nearestName = null;
        $nearestRadius = null;
        $matchedBranch = null;

        foreach ($branches as $b) {
            $dist = self::getDistance($lat, $lng, (float)$b['latitude'], (float)$b['longitude']);
            $radius = (int)($b['radius_meters'] ?? 200) + $buffer;

            if ($dist <= $radius) {
                return [
                    'allowed'     => true,
                    'branch_name' => $b['branch_name'] ?? '',
                    'distance_m'  => (int)round($dist),
                    'buffer_m'    => $buffer,
                ];
            }

            if ($nearestDist === null || $dist < $nearestDist) {
                $nearestDist = $dist;
                $nearestName = $b['branch_name'] ?? 'office';
                $nearestRadius = (int)($b['radius_meters'] ?? 200);
                $matchedBranch = $b['branch_name'] ?? '';
            }
        }

        return [
            'allowed'      => false,
            'message'      => sprintf(
                'Outside allowed area: ~%dm from "%s" (allowed %dm + %dm GPS buffer). Enable precise location or move closer.',
                (int)round($nearestDist),
                $nearestName,
                $nearestRadius,
                $buffer
            ),
            'distance_m'   => (int)round($nearestDist),
            'branch_name'  => $matchedBranch,
            'nearest_name' => $nearestName,
        ];
    }

    /**
     * Validate against staff's registered phone location (set once from dashboard).
     */
    public static function validateStaffClockZone($lat, $lng, $clockLat, $clockLng, $radiusMeters, $accuracyBuffer = 0) {
        if ($clockLat === null || $clockLng === null) {
            return ['allowed' => false];
        }
        $buffer = min(max(0, (int)$accuracyBuffer), 200);
        $radius = max(50, (int)$radiusMeters) + $buffer;
        $dist = self::getDistance($lat, $lng, (float)$clockLat, (float)$clockLng);
        if ($dist <= $radius) {
            return [
                'allowed'     => true,
                'branch_name' => 'Your registered location',
                'distance_m'  => (int)round($dist),
            ];
        }
        return [
            'allowed'    => false,
            'distance_m' => (int)round($dist),
            'radius_m'   => $radius,
        ];
    }

    /** Distances from point to all branches (for UI debug). */
    public static function distancesToBranches($lat, $lng, array $branches) {
        $out = [];
        foreach ($branches as $b) {
            $dist = self::getDistance($lat, $lng, (float)$b['latitude'], (float)$b['longitude']);
            $radius = (int)($b['radius_meters'] ?? 200);
            $out[] = [
                'name'     => $b['branch_name'] ?? '',
                'distance' => (int)round($dist),
                'radius'   => $radius,
                'inside'   => $dist <= $radius,
            ];
        }
        usort($out, fn($a, $b) => $a['distance'] <=> $b['distance']);
        return $out;
    }
}
