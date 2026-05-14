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
}
