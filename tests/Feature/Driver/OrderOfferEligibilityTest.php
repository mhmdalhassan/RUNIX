<?php

namespace Tests\Feature\Driver;

use App\Enums\OrderOfferResult;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Services\Orders\EligibleDriverFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EligibleDriverFinder is V1 (spec §5): no proximity/GPS filtering at all —
 * eligibility is purely is_active && is_online && current_order_id IS NULL,
 * plus "not already holding a PENDING offer for this exact order."
 */
class OrderOfferEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function finder(): EligibleDriverFinder
    {
        return app(EligibleDriverFinder::class);
    }

    public function test_inactive_driver_is_not_eligible(): void
    {
        Driver::factory()->create(['is_active' => false, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $this->assertCount(0, $this->finder()->forOrder($order));
    }

    public function test_offline_driver_is_not_eligible(): void
    {
        Driver::factory()->create(['is_active' => true, 'is_online' => false]);
        $order = Order::factory()->available()->create();

        $this->assertCount(0, $this->finder()->forOrder($order));
    }

    public function test_occupied_driver_is_not_eligible(): void
    {
        $busyWith = Order::factory()->accepted()->create();
        Driver::factory()->create([
            'is_active' => true,
            'is_online' => true,
            'current_order_id' => $busyWith->id,
        ]);
        $order = Order::factory()->available()->create();

        $this->assertCount(0, $this->finder()->forOrder($order));
    }

    public function test_active_online_unoccupied_driver_is_eligible(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $eligible = $this->finder()->forOrder($order);

        $this->assertCount(1, $eligible);
        $this->assertTrue($eligible->first()->is($driver));
    }

    public function test_driver_with_an_existing_pending_offer_for_this_order_is_not_offered_again(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        OrderOffer::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'result' => OrderOfferResult::PENDING,
        ]);

        $this->assertCount(0, $this->finder()->forOrder($order));
    }

    public function test_driver_with_a_pending_offer_on_another_order_is_still_eligible_here(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $otherOrder = Order::factory()->available()->create();
        $order = Order::factory()->available()->create();

        OrderOffer::factory()->create([
            'order_id' => $otherOrder->id,
            'driver_id' => $driver->id,
            'result' => OrderOfferResult::PENDING,
        ]);

        $eligible = $this->finder()->forOrder($order);

        $this->assertCount(1, $eligible);
        $this->assertTrue($eligible->first()->is($driver));
    }

    public function test_a_driver_whose_earlier_offer_expired_is_eligible_again(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        OrderOffer::factory()->expired()->create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);

        $eligible = $this->finder()->forOrder($order);

        $this->assertCount(1, $eligible);
        $this->assertTrue($eligible->first()->is($driver));
    }

    public function test_multiple_eligible_drivers_are_all_returned(): void
    {
        Driver::factory()->count(3)->create(['is_active' => true, 'is_online' => true]);
        Driver::factory()->create(['is_active' => false, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $this->assertCount(3, $this->finder()->forOrder($order));
    }

    /**
     * Phase 5 §2/§4 — proximity is a read-time ordering on top of the
     * exact same eligibility query above; it never changes who's eligible
     * (all cases below still return every driver the pre-Phase-5 tests
     * above would).
     */
    private function pickupOrder(): Order
    {
        // Beirut.
        return Order::factory()->available()->create([
            'pickup_latitude' => 33.8938,
            'pickup_longitude' => 35.5018,
        ]);
    }

    public function test_order_with_no_pickup_coordinates_falls_back_to_unsorted_v1_behavior(): void
    {
        $near = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            'last_latitude' => 33.90, 'last_longitude' => 35.51, 'last_location_at' => now(),
        ]);
        $far = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            'last_latitude' => 34.4367, 'last_longitude' => 35.8497, 'last_location_at' => now(),
        ]);
        $order = Order::factory()->available()->create([
            'pickup_latitude' => null,
            'pickup_longitude' => null,
        ]);

        $eligible = $this->finder()->forOrder($order);

        // Both still eligible — order not asserted, since there's nothing
        // to sort by (exactly today's pre-Phase-5 behavior).
        $this->assertCount(2, $eligible);
        $this->assertTrue($eligible->contains(fn (Driver $d) => $d->is($near)));
        $this->assertTrue($eligible->contains(fn (Driver $d) => $d->is($far)));
    }

    public function test_nearer_driver_is_ranked_before_farther_driver(): void
    {
        $order = $this->pickupOrder();

        $far = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            // Tripoli, Lebanon — ~68km from Beirut, still within the
            // 15km-default-radius-irrelevant "fresh, ranked by distance" tier.
            'last_latitude' => 34.4367, 'last_longitude' => 35.8497, 'last_location_at' => now(),
        ]);
        $near = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            // ~1km from the pickup point.
            'last_latitude' => 33.90, 'last_longitude' => 35.51, 'last_location_at' => now(),
        ]);

        $eligible = $this->finder()->forOrder($order);

        $this->assertCount(2, $eligible);
        $this->assertTrue($eligible->first()->is($near));
        $this->assertTrue($eligible->last()->is($far));
    }

    public function test_driver_within_radius_is_ranked_before_driver_beyond_it(): void
    {
        config(['runix.matching.max_radius_km' => 15]);
        $order = $this->pickupOrder();

        $beyondRadius = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            // ~68km away — beyond the 15km radius, but still a fresh location.
            'last_latitude' => 34.4367, 'last_longitude' => 35.8497, 'last_location_at' => now(),
        ]);
        $withinRadius = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            // ~1km away — within the 15km radius.
            'last_latitude' => 33.90, 'last_longitude' => 35.51, 'last_location_at' => now(),
        ]);

        $eligible = $this->finder()->forOrder($order);

        $this->assertTrue($eligible->first()->is($withinRadius));
        $this->assertTrue($eligible->last()->is($beyondRadius));
    }

    public function test_driver_with_stale_location_is_still_eligible_but_ranked_last(): void
    {
        config(['runix.matching.location_stale_after_minutes' => 5]);
        $order = $this->pickupOrder();

        $stale = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            // Right at the pickup point, but the fix is 10 minutes old.
            'last_latitude' => 33.8938, 'last_longitude' => 35.5018,
            'last_location_at' => now()->subMinutes(10),
        ]);
        $fresh = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            // Farther away, but the fix is fresh.
            'last_latitude' => 34.4367, 'last_longitude' => 35.8497, 'last_location_at' => now(),
        ]);

        $eligible = $this->finder()->forOrder($order);

        // Both still eligible — stale never excludes.
        $this->assertCount(2, $eligible);
        $this->assertTrue($eligible->first()->is($fresh));
        $this->assertTrue($eligible->last()->is($stale));
    }

    public function test_driver_with_no_location_at_all_is_still_eligible_but_ranked_last(): void
    {
        $order = $this->pickupOrder();

        $noLocation = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $fresh = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            'last_latitude' => 33.90, 'last_longitude' => 35.51, 'last_location_at' => now(),
        ]);

        $eligible = $this->finder()->forOrder($order);

        // Both still eligible — missing location never excludes.
        $this->assertCount(2, $eligible);
        $this->assertTrue($eligible->first()->is($fresh));
        $this->assertTrue($eligible->last()->is($noLocation));
    }

    public function test_mixed_near_far_stale_and_missing_drivers_are_all_tiered_correctly(): void
    {
        config(['runix.matching.max_radius_km' => 15, 'runix.matching.location_stale_after_minutes' => 5]);
        $order = $this->pickupOrder();

        $missing = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $stale = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            'last_latitude' => 33.8938, 'last_longitude' => 35.5018,
            'last_location_at' => now()->subMinutes(10),
        ]);
        $beyondRadius = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            'last_latitude' => 34.4367, 'last_longitude' => 35.8497, 'last_location_at' => now(),
        ]);
        $withinRadius = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            'last_latitude' => 33.90, 'last_longitude' => 35.51, 'last_location_at' => now(),
        ]);

        $eligible = $this->finder()->forOrder($order);

        $this->assertCount(4, $eligible);
        $ids = $eligible->pluck('id')->values()->all();

        $this->assertSame($withinRadius->id, $ids[0]);
        $this->assertSame($beyondRadius->id, $ids[1]);
        // The last two (missing/stale) share a tier — order between them
        // is unspecified, so only assert they're both after the fresh ones.
        $this->assertEqualsCanonicalizing([$missing->id, $stale->id], array_slice($ids, 2));
    }
}
