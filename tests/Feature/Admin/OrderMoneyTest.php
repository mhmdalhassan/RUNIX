<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OrderMoneyTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => Customer::factory()->create()->id,
            'pickup_address' => '123 Pickup Street',
            'delivery_address' => '456 Delivery Avenue',
            'delivery_fee' => 10,
            'driver_earning' => 6,
        ], $overrides);
    }

    public function test_negative_delivery_fee_is_rejected(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'delivery_fee' => -1,
        ]));

        $response->assertSessionHasErrors('delivery_fee');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_negative_driver_earning_is_rejected(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'driver_earning' => -1,
        ]));

        $response->assertSessionHasErrors('driver_earning');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_driver_earning_at_or_below_delivery_fee_is_accepted_without_override(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'delivery_fee' => 10,
            'driver_earning' => 10,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_dispatcher_cannot_set_driver_earning_above_delivery_fee(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'delivery_fee' => 5,
            'driver_earning' => 10,
            'driver_earning_override' => true,
            'driver_earning_override_reason' => 'Trying anyway',
        ]));

        $response->assertSessionHasErrors('driver_earning');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_super_admin_can_approve_a_driver_earning_override(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.orders.store'), $this->validPayload([
            'delivery_fee' => 5,
            'driver_earning' => 10,
            'driver_earning_override' => true,
            'driver_earning_override_reason' => 'Long distance surcharge',
        ]));

        $response->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame('10.00', $order->driver_earning);
        $this->assertTrue($order->driver_earning_override);
        $this->assertSame('Long distance surcharge', $order->driver_earning_override_reason);
    }

    public function test_super_admin_override_without_confirming_the_checkbox_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.orders.store'), $this->validPayload([
            'delivery_fee' => 5,
            'driver_earning' => 10,
            'driver_earning_override' => false,
            'driver_earning_override_reason' => 'Has a reason but did not confirm',
        ]));

        $response->assertSessionHasErrors('driver_earning_override');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_override_requires_a_reason(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.orders.store'), $this->validPayload([
            'delivery_fee' => 5,
            'driver_earning' => 10,
            'driver_earning_override' => true,
            'driver_earning_override_reason' => '',
        ]));

        $response->assertSessionHasErrors('driver_earning_override_reason');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_driver_earning_set_by_is_recorded_on_creation(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload());

        $order = Order::firstOrFail();
        $this->assertSame($dispatcher->id, $order->driver_earning_set_by);
    }

    public function test_driver_earning_set_by_updates_to_the_user_who_edits_it(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $admin = User::factory()->superAdmin()->create();
        $order = Order::factory()->create(['delivery_fee' => 10, 'driver_earning' => 5, 'driver_earning_set_by' => $dispatcher->id]);

        $this->actingAs($admin)->put(route('admin.orders.update', $order), [
            'pickup_address' => $order->pickup_address,
            'delivery_address' => $order->delivery_address,
            'delivery_fee' => 10,
            'driver_earning' => 7,
            'fee_payer' => $order->fee_payer->value,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
        ]);

        $order->refresh();
        $this->assertSame('7.00', $order->driver_earning);
        $this->assertSame($admin->id, $order->driver_earning_set_by);
    }

    // --- COD configuration (§4) -----------------------------------------

    public function test_cod_amounts_are_forced_to_zero_when_cod_is_disabled(): void
    {
        Config::set('runix.cod_enabled', false);
        $dispatcher = User::factory()->dispatcher()->create();

        // merchant_amount/cod_amount aren't even sent — the UI hides them
        // — but this proves the server-side force applies regardless.
        $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload());

        $order = Order::firstOrFail();
        $this->assertSame('0.00', $order->merchant_amount);
        $this->assertSame('0.00', $order->cod_amount);
    }

    public function test_cod_amounts_are_rejected_outright_when_cod_is_disabled(): void
    {
        Config::set('runix.cod_enabled', false);
        $dispatcher = User::factory()->dispatcher()->create();

        // Even a client that bypasses the UI and sends COD fields anyway
        // gets a validation error, not silent acceptance.
        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'merchant_amount' => 5,
            'cod_amount' => 5,
        ]));

        $response->assertSessionHasErrors(['merchant_amount', 'cod_amount']);
    }

    public function test_cod_amounts_are_honored_when_cod_is_enabled(): void
    {
        Config::set('runix.cod_enabled', true);
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'merchant_amount' => 12.50,
            'cod_amount' => 12.50,
        ]));

        $order = Order::firstOrFail();
        $this->assertSame('12.50', $order->merchant_amount);
        $this->assertSame('12.50', $order->cod_amount);
    }
}
