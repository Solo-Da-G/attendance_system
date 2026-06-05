<?php
/**
 * GEOLOCATION LIBRARY
 * 
 * Logic to calculate distances between coordinates.
 */

class Geolocation {

    /**
     * Calculate distance between two points (Haversine Formula)
     * 
     * @param float $lat1 Latitude of point 1
     * @param float $lon1 Longitude of point 1
     * @param float $lat2 Latitude of point 2
     * @param float $lon2 Longitude of point 2
     * @return float Distance in meters
     */
    public static function getDistance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371000; // Earth radius in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earth_radius * $c;

        return $distance;
    }

    /**
     * Check if a point is within a geofence radius
     * 
     * @param float $userLat User current latitude
     * @param float $userLng User current longitude
     * @param float $officeLat Office latitude
     * @param float $officeLng Office longitude
     * @param int $radius Allowed radius in meters
     * @return bool
     */
    public static function isWithinRadius($userLat, $userLng, $officeLat, $officeLng, $radius = 200) {
        $distance = self::getDistance($userLat, $userLng, $officeLat, $officeLng);
        return $distance <= $radius;
    }
}
