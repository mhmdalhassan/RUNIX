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
}
