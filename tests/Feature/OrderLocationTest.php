<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /track/{order:tracking_token}/location — the public order-tracking
 * page's live-map data source (resources/js/runix/order-tracking-map.js
 * polls it; App\Events\DriverLocationUpdated is just a "refresh sooner"
 * layer on top, not covered here — see UpdateDriverLocationTest for
 * that). Same unauthenticated, resolved-by-tracking_token shape as
 * OrderTrackingController — see OrderTrackingTest for the id/token
 * disclosure guards this endpoint is equally bound by.
 */
class OrderLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_with_no_driver_yet_is_not_trackable(): void
    {
        $order = Order::factory()->available()->create();

        $response = $this->getJson(route('track.location', $order->tracking_token));

        $response->assertOk();
        $response->assertJson(['trackable' => false]);
    }

    public function test_a_delivered_order_is_no_longer_trackable(): void
    {
        $order = Order::factory()->delivered()->create();

        $response = $this->getJson(route('track.location', $order->tracking_token));

        $response->assertOk();
        $response->assertJson(['trackable' => false]);
    }

    public function test_a_cancelled_order_is_no_longer_trackable(): void
    {
        $order = Order::factory()->cancelled()->create();

        $response = $this->getJson(route('track.location', $order->tracking_token));

        $response->assertJson(['trackable' => false]);
    }

    public function test_an_accepted_order_whose_driver_has_no_gps_fix_yet_is_trackable_but_locationless(): void
    {
        $driver = Driver::factory()->create([
            'last_latitude' => null,
            'last_longitude' => null,
        ]);
        $order = Order::factory()->accepted()->create(['driver_id' => $driver->id]);

        $response = $this->getJson(route('track.location', $order->tracking_token));

        $response->assertOk();
        $response->assertJson(['trackable' => true, 'has_location' => false]);
    }

    public function test_an_accepted_order_with_a_located_driver_returns_coordinates(): void
    {
        $driver = Driver::factory()->create([
            'last_latitude' => 33.8938123,
            'last_longitude' => 35.5018456,
            'last_location_at' => now(),
        ]);
        $order = Order::factory()->accepted()->create(['driver_id' => $driver->id]);

        $response = $this->getJson(route('track.location', $order->tracking_token));

        $response->assertOk();
        $response->assertJson(['trackable' => true, 'has_location' => true]);
        $this->assertEqualsWithDelta(33.8938123, $response->json('latitude'), 0.0001);
        $this->assertEqualsWithDelta(35.5018456, $response->json('longitude'), 0.0001);
        $this->assertNotNull($response->json('updated_at'));
    }

    public function test_picked_up_and_on_the_way_orders_are_also_trackable(): void
    {
        $driver = Driver::factory()->create(['last_latitude' => 1.0, 'last_longitude' => 1.0]);

        $pickedUp = Order::factory()->pickedUp()->create(['driver_id' => $driver->id]);
        $onTheWay = Order::factory()->onTheWay()->create(['driver_id' => $driver->id]);

        $this->getJson(route('track.location', $pickedUp->tracking_token))
            ->assertJson(['trackable' => true, 'has_location' => true]);

        $this->getJson(route('track.location', $onTheWay->tracking_token))
            ->assertJson(['trackable' => true, 'has_location' => true]);
    }

    public function test_the_endpoint_never_exposes_the_driver_beyond_their_coordinates(): void
    {
        $driverUser = User::factory()->driver()->create(['name' => 'Ultra Secret Driver Name']);
        $driver = Driver::factory()->create([
            'user_id' => $driverUser->id,
            'phone' => '+96171888888',
            'last_latitude' => 1.0,
            'last_longitude' => 1.0,
        ]);
        $order = Order::factory()->accepted()->create(['driver_id' => $driver->id]);

        $response = $this->getJson(route('track.location', $order->tracking_token));

        $response->assertOk();
        $response->assertDontSee('Ultra Secret Driver Name');
        $response->assertDontSee('+96171888888');
    }

    public function test_an_invalid_token_returns_404(): void
    {
        $response = $this->getJson('/track/this-token-does-not-exist/location');

        $response->assertNotFound();
    }

    public function test_requires_no_authentication(): void
    {
        $order = Order::factory()->accepted()->create();

        $response = $this->getJson(route('track.location', $order->tracking_token));

        $response->assertOk();
    }

    public function test_a_pending_order_is_not_trackable_either(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $response = $this->getJson(route('track.location', $order->tracking_token));

        $response->assertJson(['trackable' => false]);
    }
}
