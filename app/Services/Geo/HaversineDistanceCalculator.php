<?php

namespace App\Services\Geo;

/**
 * Great-circle distance between two lat/lng points (Phase 5 §2). Pure
 * math, no I/O, no dependency on Driver/Order — kept generic and
 * independently testable rather than folded into EligibleDriverFinder.
 *
 * Straight-line distance, not a routed/driving distance — there's no
 * routing API in this project (see orders.distance_km's own migration
 * comment); this is only ever used to rank already-eligible drivers, not
 * to promise an accurate travel distance.
 */
class HaversineDistanceCalculator
{
    /**
     * Mean Earth radius in kilometers.
     */
    private const EARTH_RADIUS_KM = 6371.0;

    public function distanceInKm(float $latitudeOne, float $longitudeOne, float $latitudeTwo, float $longitudeTwo): float
    {
        if ($latitudeOne === $latitudeTwo && $longitudeOne === $longitudeTwo) {
            return 0.0;
        }

        $latDelta = deg2rad($latitudeTwo - $latitudeOne);
        $lonDelta = deg2rad($longitudeTwo - $longitudeOne);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($latitudeOne)) * cos(deg2rad($latitudeTwo)) * sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
