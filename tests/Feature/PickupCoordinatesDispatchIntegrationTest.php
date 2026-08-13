<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 6 §8 — proves the pickup-coordinate pipeline end to end through
 * the real HTTP flow, not just at the model/factory level: dispatcher
 * submits pickup coordinates through StoreOrderRequest ->
 * CreateOrderService writes them -> transitioning the order to AVAILABLE
 * runs OfferOrderService -> EligibleDriverFinder (Phase 5, untouched)
 * ranks eligible drivers by proximity to those exact coordinates.
 *
 * OfferOrderService creates one OrderOffer per eligible driver in the
 * order EligibleDriverFinder returns them (see OfferOrderService::offer()),
 * so the OrderOffer creation order is a faithful proxy for the ranking
 * without reaching into EligibleDriverFinder directly.
 */
class PickupCoordinatesDispatchIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_created_with_pickup_coordinates_offers_the_nearer_eligible_driver_first(): void
    {
        Queue::fake();
        // Pinned explicitly (same pattern OrderOfferEligibilityTest uses)
        // rather than relying on config/runix.php's defaults, so this test
        // can never be affected by env overrides.
        config(['runix.matching.max_radius_km' => 15, 'runix.matching.location_stale_after_minutes' => 5]);

        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        // Beirut, Tripoli — same coordinates already used by Phase 5's own
        // EligibleDriverFinder tests, so the distances here are known-good.
        $far = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            'last_latitude' => 34.4367, 'last_longitude' => 35.8497, 'last_location_at' => now(),
        ]);
        $near = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            'last_latitude' => 33.90, 'last_longitude' => 35.51, 'last_location_at' => now(),
        ]);

        $storeResponse = $this->actingAs($dispatcher)->post(route('admin.orders.store'), [
            'customer_id' => $customer->id,
            'pickup_address' => '123 Pickup Street, Beirut',
            'pickup_latitude' => 33.8938,
            'pickup_longitude' => 35.5018,
            'delivery_address' => '456 Delivery Avenue, Beirut',
            'delivery_fee' => 10,
            'driver_earning' => 6,
        ]);
        $storeResponse->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertEquals(33.8938, (float) $order->pickup_latitude);
        $this->assertEquals(35.5018, (float) $order->pickup_longitude);

        $transitionResponse = $this->actingAs($dispatcher)->patch(route('admin.orders.transition', $order), [
            'to_status' => 'available',
        ]);
        $transitionResponse->assertRedirect();

        $offeredDriverIds = $order->offers()->orderBy('id')->pluck('driver_id')->all();

        $this->assertSame([$near->id, $far->id], $offeredDriverIds);
    }

    /**
     * Phase 5's own eligibility rules (Tier 0/1/2) stay intact when
     * reached through this real HTTP path: a driver with no location is
     * still offered, just ranked after every driver with a fresh one.
     */
    public function test_driver_with_no_location_is_still_offered_but_ranked_last(): void
    {
        Queue::fake();
        config(['runix.matching.max_radius_km' => 15, 'runix.matching.location_stale_after_minutes' => 5]);

        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        $withLocation = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            'last_latitude' => 33.90, 'last_longitude' => 35.51, 'last_location_at' => now(),
        ]);
        $withoutLocation = Driver::factory()->create([
            'is_active' => true, 'is_online' => true,
            'last_latitude' => null, 'last_longitude' => null, 'last_location_at' => null,
        ]);

        $this->actingAs($dispatcher)->post(route('admin.orders.store'), [
            'customer_id' => $customer->id,
            'pickup_address' => '123 Pickup Street, Beirut',
            'pickup_latitude' => 33.8938,
            'pickup_longitude' => 35.5018,
            'delivery_address' => '456 Delivery Avenue, Beirut',
            'delivery_fee' => 10,
            'driver_earning' => 6,
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->actingAs($dispatcher)->patch(route('admin.orders.transition', $order), [
            'to_status' => 'available',
        ])->assertRedirect();

        $offeredDriverIds = $order->offers()->orderBy('id')->pluck('driver_id')->all();

        $this->assertSame([$withLocation->id, $withoutLocation->id], $offeredDriverIds);
    }
}
