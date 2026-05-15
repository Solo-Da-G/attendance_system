<?php
/**
 * Geolocation Helper Class
 */
class Geolocation {
    /**
     * Calculate distance between two points using Haversine formula
     */
    public static function getDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000; // in meters

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    /**
     * Check if a point is within a radius of another point
     */
    public static function isWithinRadius($lat1, $lon1, $lat2, $lon2, $radiusMeters) {
        $distance = self::getDistance($lat1, $lon1, $lat2, $lon2);
        return $distance <= $radiusMeters;
    }

    /**
     * Check if coords are inside any branch; returns result with distance details.
     */
    public static function validateAgainstBranches($lat, $lng, array $branches) {
        if (empty($branches)) {
            return ['allowed' => true, 'message' => null];
        }

        $nearestDist = null;
        $nearestName = null;
        $nearestRadius = null;

        foreach ($branches as $b) {
            $dist = self::getDistance($lat, $lng, (float)$b['latitude'], (float)$b['longitude']);
            $radius = (int)($b['radius_meters'] ?? 200);

            if ($dist <= $radius) {
                return [
                    'allowed' => true,
                    'branch_name' => $b['branch_name'] ?? '',
                    'distance_m' => (int)round($dist),
                ];
            }

            if ($nearestDist === null || $dist < $nearestDist) {
                $nearestDist = $dist;
                $nearestName = $b['branch_name'] ?? 'office';
                $nearestRadius = $radius;
            }
        }

        $metersOver = (int)round($nearestDist - $nearestRadius);
        return [
            'allowed' => false,
            'message' => sprintf(
                'You are outside the allowed area (%dm from %s; limit %dm). Move closer and try again.',
                max(0, $metersOver),
                $nearestName,
                $nearestRadius
            ),
            'distance_m' => (int)round($nearestDist),
        ];
    }
}
