<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 §1/§6 — pickup_latitude/pickup_longitude reaching the database
 * through the real order creation/update flow, with the same validation
 * rules spec §1 asks for: nullable, numeric, in-range, and never a
 * partial pair. Store/update service behavior itself
 * (CreateOrderService/UpdateOrderService) is unchanged by Phase 6 — these
 * tests exercise the request validation and controller wiring on top of
 * it, the same way OrderManagementTest/OrderUpdateLockingTest do for the
 * rest of the order fields.
 */
class OrderPickupCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'pickup_address' => '123 Pickup Street, Beirut',
            'delivery_address' => '456 Delivery Avenue, Beirut',
            'delivery_fee' => 10,
            'driver_earning' => 6,
        ], $overrides);
    }

    // --- Store: valid coordinates -------------------------------------

    public function test_order_creation_stores_valid_pickup_coordinates(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
            'pickup_latitude' => 33.8938,
            'pickup_longitude' => 35.5018,
        ]));

        $response->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertEquals(33.8938, (float) $order->pickup_latitude);
        $this->assertEquals(35.5018, (float) $order->pickup_longitude);
    }

    public function test_order_creation_allows_both_pickup_coordinates_to_be_null(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
        ]));

        $response->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertNull($order->pickup_latitude);
        $this->assertNull($order->pickup_longitude);
    }

    // --- Store: range validation ----------------------------------------

    public function test_pickup_latitude_outside_range_is_rejected(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
            'pickup_latitude' => 90.5,
            'pickup_longitude' => 35.5018,
        ]));

        $response->assertSessionHasErrors('pickup_latitude');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_pickup_longitude_outside_range_is_rejected(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
            'pickup_latitude' => 33.8938,
            'pickup_longitude' => 180.5,
        ]));

        $response->assertSessionHasErrors('pickup_longitude');
        $this->assertDatabaseCount('orders', 0);
    }

    // --- Store: pairing ---------------------------------------------------

    public function test_pickup_latitude_without_longitude_is_rejected(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
            'pickup_latitude' => 33.8938,
        ]));

        $response->assertSessionHasErrors('pickup_longitude');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_pickup_longitude_without_latitude_is_rejected(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
            'pickup_longitude' => 35.5018,
        ]));

        $response->assertSessionHasErrors('pickup_latitude');
        $this->assertDatabaseCount('orders', 0);
    }

    // --- Update: unlocked (PENDING/AVAILABLE) ------------------------------

    public function test_dispatcher_can_set_pickup_coordinates_on_a_pending_order(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->create([
            'pickup_latitude' => null,
            'pickup_longitude' => null,
        ]);

        $response = $this->actingAs($dispatcher)->put(route('admin.orders.update', $order), [
            'pickup_address' => $order->pickup_address,
            'delivery_address' => $order->delivery_address,
            'delivery_fee' => $order->delivery_fee,
            'driver_earning' => $order->driver_earning,
            'pickup_latitude' => 33.8938,
            'pickup_longitude' => 35.5018,
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals(33.8938, (float) $order->pickup_latitude);
        $this->assertEquals(35.5018, (float) $order->pickup_longitude);
    }

    public function test_dispatcher_can_update_existing_pickup_coordinates(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->create([
            'pickup_latitude' => 33.8938,
            'pickup_longitude' => 35.5018,
        ]);

        $response = $this->actingAs($dispatcher)->put(route('admin.orders.update', $order), [
            'pickup_address' => $order->pickup_address,
            'delivery_address' => $order->delivery_address,
            'delivery_fee' => $order->delivery_fee,
            'driver_earning' => $order->driver_earning,
            'pickup_latitude' => 34.4367,
            'pickup_longitude' => 35.8497,
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals(34.4367, (float) $order->pickup_latitude);
        $this->assertEquals(35.8497, (float) $order->pickup_longitude);
    }

    public function test_update_rejects_a_partial_pickup_coordinate_pair(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->create([
            'pickup_latitude' => null,
            'pickup_longitude' => null,
        ]);

        $response = $this->actingAs($dispatcher)->put(route('admin.orders.update', $order), [
            'pickup_address' => $order->pickup_address,
            'delivery_address' => $order->delivery_address,
            'delivery_fee' => $order->delivery_fee,
            'driver_earning' => $order->driver_earning,
            'pickup_latitude' => 33.8938,
        ]);

        $response->assertSessionHasErrors('pickup_longitude');
        $order->refresh();
        $this->assertNull($order->pickup_latitude);
    }

    // --- Update: locked (past AVAILABLE) — existing locking rules intact --

    public function test_accepted_order_rejects_pickup_coordinate_changes(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->accepted()->create([
            'pickup_latitude' => 33.8938,
            'pickup_longitude' => 35.5018,
        ]);

        $response = $this->actingAs($dispatcher)->put(route('admin.orders.update', $order), [
            'fee_payer' => $order->fee_payer->value,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
            'pickup_latitude' => 34.4367,
            'pickup_longitude' => 35.8497,
        ]);

        $response->assertSessionHasErrors(['pickup_latitude', 'pickup_longitude']);
        $order->refresh();
        $this->assertEquals(33.8938, (float) $order->pickup_latitude);
        $this->assertEquals(35.5018, (float) $order->pickup_longitude);
    }

    public function test_locked_order_update_without_coordinates_still_succeeds(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->accepted()->create([
            'pickup_latitude' => 33.8938,
            'pickup_longitude' => 35.5018,
        ]);

        // Existing §16 locking behavior (untouched by Phase 6): a locked
        // order can still be updated as long as it doesn't touch a
        // protected field.
        $response = $this->actingAs($dispatcher)->put(route('admin.orders.update', $order), [
            'fee_payer' => $order->fee_payer->value,
            'payment_method' => $order->payment_method->value,
            'payment_status' => 'collected',
            'internal_notes' => 'Fragile package',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertSame('collected', $order->payment_status->value);
        $this->assertEquals(33.8938, (float) $order->pickup_latitude);
    }
}
