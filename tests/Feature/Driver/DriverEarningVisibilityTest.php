<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A driver's actual pay is orders.driver_earning, not orders.delivery_fee
 * — the two are deliberately allowed to differ (config('runix.
 * customer_ordering') even defaults them to different flat values). Every
 * driver-facing surface that shows money for a job should make the
 * earning, not the fee, the number the driver actually relies on.
 */
class DriverEarningVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_FEE = 12.00;

    private const DRIVER_EARNING = 8.50;

    public function test_the_offer_card_shows_the_drivers_actual_earning(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create([
            'delivery_fee' => self::DELIVERY_FEE,
            'driver_earning' => self::DRIVER_EARNING,
        ]);
        OrderOffer::factory()->create(['order_id' => $order->id, 'driver_id' => $driver->id]);

        $this->actingAs($driver->user)
            ->get(route('driver.offers.index'))
            ->assertOk()
            ->assertSee('Your Earning')
            ->assertSee('$'.number_format(self::DRIVER_EARNING, 2))
            ->assertSee('$'.number_format(self::DELIVERY_FEE, 2)); // still shown, as context
    }

    public function test_the_available_orders_board_shows_the_drivers_actual_earning(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        Order::factory()->available()->create([
            'delivery_fee' => self::DELIVERY_FEE,
            'driver_earning' => self::DRIVER_EARNING,
        ]);

        $this->actingAs($driver->user)
            ->get(route('driver.orders.available'))
            ->assertOk()
            ->assertSee('Your Earning')
            ->assertSee('$'.number_format(self::DRIVER_EARNING, 2));
    }

    public function test_the_order_show_page_shows_the_drivers_actual_earning_and_not_the_delivery_fee(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->accepted()->create([
            'driver_id' => $driver->id,
            'delivery_fee' => self::DELIVERY_FEE,
            'driver_earning' => self::DRIVER_EARNING,
        ]);
        $driver->update(['current_order_id' => $order->id]);

        $this->actingAs($driver->user)
            ->get(route('driver.orders.show', $order))
            ->assertOk()
            ->assertSee('Your Earning')
            ->assertSee('$'.number_format(self::DRIVER_EARNING, 2))
            // delivery_fee stays admin/dispatcher-only here — what the
            // customer paid isn't the driver's business post-acceptance.
            ->assertDontSee('$'.number_format(self::DELIVERY_FEE, 2));
    }

    public function test_the_dashboard_shows_the_drivers_actual_earning_for_their_current_order(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->accepted()->create([
            'driver_id' => $driver->id,
            'delivery_fee' => self::DELIVERY_FEE,
            'driver_earning' => self::DRIVER_EARNING,
        ]);
        $driver->update(['current_order_id' => $order->id]);

        $this->actingAs($driver->user)
            ->get(route('driver.dashboard'))
            ->assertOk()
            ->assertSee('Your Earning')
            ->assertSee('$'.number_format(self::DRIVER_EARNING, 2));
    }
}
