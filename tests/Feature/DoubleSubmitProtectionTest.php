<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The Alpine `preventDoubleSubmit` component (resources/js/runix/
 * prevent-double-submit.js) is a client-side UX guard — PHPUnit can't
 * exercise the actual click/submit behavior (that's Playwright's job),
 * but it can prove the markup opts each of the six important forms into
 * it, which is the one thing a silent Blade edit could regress without
 * any other test noticing.
 */
class DoubleSubmitProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const MARKER = 'x-data="preventDoubleSubmit"';

    public function test_driver_offer_accept_and_reject_forms_are_guarded(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();
        OrderOffer::factory()->create(['order_id' => $order->id, 'driver_id' => $driver->id]);

        $this->actingAs($driver->user)
            ->get(route('driver.offers.index'))
            ->assertOk()
            ->assertSeeInOrder([self::MARKER, self::MARKER], false); // one per form: reject, then accept
    }

    public function test_available_order_claim_form_is_guarded(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        Order::factory()->available()->create();

        $this->actingAs($driver->user)
            ->get(route('driver.orders.available'))
            ->assertOk()
            ->assertSee(self::MARKER, false);
    }

    public function test_driver_release_form_is_guarded(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->accepted()->create(['driver_id' => $driver->id]);
        $driver->update(['current_order_id' => $order->id]);

        $this->actingAs($driver->user)
            ->get(route('driver.orders.show', $order))
            ->assertOk()
            ->assertSee(self::MARKER, false);
    }

    public function test_admin_order_create_form_is_guarded(): void
    {
        $dispatcher = User::factory()->create(['role' => UserRole::DISPATCHER]);

        $this->actingAs($dispatcher)
            ->get(route('admin.orders.create'))
            ->assertOk()
            ->assertSee(self::MARKER, false);
    }

    public function test_customer_checkout_form_is_guarded(): void
    {
        $customer = Customer::factory()->withAccount()->create();
        Auth::guard('customer')->login($customer);

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertSee(self::MARKER, false);
    }
}
