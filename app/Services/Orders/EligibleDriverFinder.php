<?php

namespace App\Services\Orders;

use App\Enums\OrderOfferResult;
use App\Models\Driver;
use App\Models\Order;
use App\Services\Geo\HaversineDistanceCalculator;
use Illuminate\Database\Eloquent\Collection;

/**
 * V1 eligibility (spec §5/§2), unchanged since Phase 4: every active,
 * online, unoccupied driver without an existing PENDING offer on this
 * order is eligible — full stop. Phase 5 adds proximity-aware *ordering*
 * of that same result (see sortByProximity() below); it never changes who
 * is eligible. A driver missing a location, holding a stale one, or
 * outside the match radius is never excluded — see
 * config('runix.matching') and this class's own tests for the exact tiers.
 */
class EligibleDriverFinder
{
    public function __construct(
        private readonly HaversineDistanceCalculator $distanceCalculator,
    ) {}

    /**
     * @return Collection<int, Driver>
     */
    public function forOrder(Order $order): Collection
    {
        $drivers = Driver::query()
            ->where('is_active', true)
            ->where('is_online', true)
            ->whereNull('current_order_id')
            ->whereDoesntHave('offers', function ($query) use ($order) {
                $query->where('order_id', $order->id)
                    ->where('result', OrderOfferResult::PENDING->value);
            })
            ->get();

        return $this->sortByProximity($drivers, $order);
    }

    /**
     * Phase 5 §2/§4 — read-time ranking only, applied on top of the
     * unchanged query above. Three tiers, nearest-first within each:
     *   0. Fresh location, within config('runix.matching.max_radius_km')
     *   1. Fresh location, beyond that radius
     *   2. No location at all, or one older than
     *      config('runix.matching.location_stale_after_minutes')
     * Tier 2's internal order is whatever the query above returned it in
     * (undefined/stable) — matching the exact pre-Phase-5 behavior for
     * drivers who fall in it, since there's nothing meaningful to rank by.
     *
     * @param  Collection<int, Driver>  $drivers
     * @return Collection<int, Driver>
     */
    private function sortByProximity(Collection $drivers, Order $order): Collection
    {
        if ($order->pickup_latitude === null || $order->pickup_longitude === null) {
            return $drivers;
        }

        return $drivers
            ->sort(fn (Driver $a, Driver $b) => $this->rank($a, $order) <=> $this->rank($b, $order))
            ->values();
    }

    /**
     * @return array{0: int, 1: float} [tier, distanceKm]. distanceKm is
     *                                 only meaningful (and only ever compared) within tier 0/1 —
     *                                 PHP's <=> on these two-element arrays compares tier first,
     *                                 so it's never reached once tiers differ.
     */
    private function rank(Driver $driver, Order $order): array
    {
        if (! $this->hasFreshLocation($driver)) {
            return [2, 0.0];
        }

        $distanceKm = $this->distanceCalculator->distanceInKm(
            (float) $order->pickup_latitude,
            (float) $order->pickup_longitude,
            (float) $driver->last_latitude,
            (float) $driver->last_longitude,
        );

        $withinRadius = $distanceKm <= (float) config('runix.matching.max_radius_km');

        return [$withinRadius ? 0 : 1, $distanceKm];
    }

    private function hasFreshLocation(Driver $driver): bool
    {
        if ($driver->last_latitude === null || $driver->last_longitude === null || $driver->last_location_at === null) {
            return false;
        }

        $staleAfterMinutes = (int) config('runix.matching.location_stale_after_minutes');

        return $driver->last_location_at->gte(now()->subMinutes($staleAfterMinutes));
    }
}
