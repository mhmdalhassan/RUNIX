<?php

namespace App\Services\Drivers;

use App\Events\DriverLocationUpdated;
use App\Models\Driver;

/**
 * Persists a driver's self-reported current location (Phase 5 §1).
 *
 * Lat/lng/accuracy structural validity (numeric, in-range) is already
 * enforced by App\Http\Requests\Driver\UpdateDriverLocationRequest before
 * this runs — this service only owns the one business rule that isn't a
 * plain "is this shape valid" check: a fix worse than the configured
 * accuracy ceiling is silently dropped rather than rejected. The HTTP
 * request still succeeds either way (§1 of the approved spec) — a bad GPS
 * fix should never surface as an error to the driver, it should just not
 * move the needle.
 */
class UpdateDriverLocationService
{
    /**
     * @param  array{latitude: float, longitude: float, accuracy: float}  $data  Already validated by UpdateDriverLocationRequest.
     */
    public function update(Driver $driver, array $data): Driver
    {
        $maxAccuracyMeters = (float) config('runix.matching.max_location_accuracy_meters');

        if ($data['accuracy'] > $maxAccuracyMeters) {
            return $driver;
        }

        $driver->update([
            'last_latitude' => $data['latitude'],
            'last_longitude' => $data['longitude'],
            'last_accuracy' => $data['accuracy'],
            'last_location_at' => now(),
        ]);

        // current_order_id is only ever non-null while that order is
        // ACCEPTED/PICKED_UP/ON_THE_WAY (see ClaimOrderForDriverService/
        // OrderTransitionService — it's cleared the instant an order
        // reaches a terminal status), so this check alone is enough to
        // know "there's a customer who could be watching this on the
        // public tracking page" without a second query to re-check the
        // order's own status.
        if ($driver->current_order_id !== null) {
            DriverLocationUpdated::dispatch($driver->current_order_id, $data['latitude'], $data['longitude']);
        }

        return $driver;
    }
}
