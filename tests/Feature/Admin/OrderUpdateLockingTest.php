<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec §16: once an order has moved past PENDING/AVAILABLE, its customer
 * snapshot/pickup/delivery/delivery_fee/driver_earning stop being
 * casually editable. Enforced server-side by UpdateOrderRequest, not just
 * hidden in the UI — these tests submit the protected fields directly.
 */
class OrderUpdateLockingTest extends TestCase
{
    use RefreshDatabase;

    private function editablePayload(Order $order, array $overrides = []): array
    {
        return array_merge([
            'pickup_address' => 'Changed pickup address',
            'delivery_address' => 'Changed delivery address',
            'delivery_fee' => 99,
            'driver_earning' => 50,
            'fee_payer' => $order->fee_payer->value,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
        ], $overrides);
    }

    public function test_pending_order_fields_are_fully_editable(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->create();

        $response = $this->actingAs($dispatcher)->put(route('admin.orders.update', $order), $this->editablePayload($order));

        $response->assertRedirect();
        $order->refresh();
        $this->assertSame('Changed pickup address', $order->pickup_address);
        $this->assertSame('99.00', $order->delivery_fee);
    }

    public function test_available_order_fields_are_still_fully_editable(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->available()->create();

        $response = $this->actingAs($dispatcher)->put(route('admin.orders.update', $order), $this->editablePayload($order));

        $response->assertRedirect();
        $this->assertSame('Changed pickup address', $order->fresh()->pickup_address);
    }

    public function test_accepted_order_protected_fields_are_locked(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->accepted()->create([
            'pickup_address' => 'Original pickup',
            'delivery_address' => 'Original delivery',
            'delivery_fee' => 10,
            'driver_earning' => 6,
        ]);

        $response = $this->actingAs($dispatcher)->put(
            route('admin.orders.update', $order),
            $this->editablePayload($order),
        );

        $response->assertSessionHasErrors(['pickup_address', 'delivery_address', 'delivery_fee', 'driver_earning']);
        $order->refresh();
        $this->assertSame('Original pickup', $order->pickup_address);
        $this->assertSame('Original delivery', $order->delivery_address);
        $this->assertSame('10.00', $order->delivery_fee);
        $this->assertSame('6.00', $order->driver_earning);
    }

    public function test_picked_up_order_protected_fields_stay_locked(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->pickedUp()->create(['pickup_address' => 'Original pickup']);

        $response = $this->actingAs($dispatcher)->put(
            route('admin.orders.update', $order),
            $this->editablePayload($order),
        );

        $response->assertSessionHasErrors('pickup_address');
        $this->assertSame('Original pickup', $order->fresh()->pickup_address);
    }

    public function test_delivered_order_protected_fields_stay_locked(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->delivered()->create(['delivery_fee' => 10]);

        $response = $this->actingAs($dispatcher)->put(
            route('admin.orders.update', $order),
            $this->editablePayload($order),
        );

        $response->assertSessionHasErrors('delivery_fee');
        $this->assertSame('10.00', $order->fresh()->delivery_fee);
    }

    public function test_notes_and_payment_status_remain_editable_once_locked(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->accepted()->create();

        $response = $this->actingAs($dispatcher)->put(route('admin.orders.update', $order), [
            'fee_payer' => $order->fee_payer->value,
            'payment_method' => $order->payment_method->value,
            'payment_status' => 'collected',
            'customer_notes' => 'Call before arriving',
            'internal_notes' => 'Fragile package',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertSame('collected', $order->payment_status->value);
        $this->assertSame('Call before arriving', $order->customer_notes);
        $this->assertSame('Fragile package', $order->internal_notes);
    }
}
