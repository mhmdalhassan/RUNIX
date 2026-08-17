<?php

namespace Tests\Feature\Driver;

use App\Enums\OrderOfferResult;
use App\Enums\OrderStatus;
use App\Events\OrderAvailable;
use App\Events\OrderTaken;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Services\Orders\OfferOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The shared "Available Orders" board — App\Http\Controllers\Driver\
 * AvailableOrdersController — added alongside the pre-existing private
 * offer inbox (still covered by OrderOfferWorkflowTest/
 * OrderOfferEligibilityTest, untouched). The one thing that
 * distinguishes the board from that inbox and is worth proving here:
 * every driver sees the exact same Order::available() list regardless of
 * whether OfferOrderService happened to create them an OrderOffer row.
 *
 * The atomic "only one claimant wins" guarantee itself is not re-proven
 * with a second real multi-process race here — ClaimAvailableOrderService
 * delegates to the exact same ClaimOrderForDriverService that
 * ConcurrentOrderAcceptanceTest already proves that for against real
 * MySQL; this file only proves the sequential-loser case (a clean
 * exception, not a crash) plus the board-specific bookkeeping around it.
 */
class AvailableOrdersTest extends TestCase
{
    use RefreshDatabase;

    // --- Board visibility ---------------------------------------------------

    public function test_board_shows_an_available_order_even_to_a_driver_who_was_never_individually_offered_it(): void
    {
        // Offline when the order was published, so EligibleDriverFinder
        // never created an OrderOffer for this driver — the private
        // inbox would never show them this order. The board must show
        // it anyway once they come online, since that's the entire
        // point of a shared board over a per-driver offer.
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => false]);
        $order = Order::factory()->available()->create();

        $this->assertDatabaseMissing('order_offers', ['order_id' => $order->id, 'driver_id' => $driver->id]);

        $driver->update(['is_online' => true]);

        $this->actingAs($driver->user)->get(route('driver.orders.available'))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_board_omits_orders_that_are_not_available(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $pending = Order::factory()->create(); // still pending, never published
        $delivered = Order::factory()->delivered()->create();

        $response = $this->actingAs($driver->user)->get(route('driver.orders.available'));

        $response->assertDontSee($pending->order_number);
        $response->assertDontSee($delivered->order_number);
    }

    public function test_publishing_an_order_broadcasts_order_available(): void
    {
        Event::fake([OrderAvailable::class]);

        $order = Order::factory()->available()->create();
        app(OfferOrderService::class)->offer($order);

        Event::assertDispatched(OrderAvailable::class, fn ($event) => $event->orderId === $order->id);
    }

    // --- Claiming -------------------------------------------------------------

    public function test_an_eligible_driver_can_claim_an_available_order_with_no_prior_offer(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $this->actingAs($driver->user)
            ->post(route('driver.orders.available.claim', $order))
            ->assertRedirect(route('driver.dashboard'));

        $order->refresh();
        $driver->refresh();

        $this->assertTrue($order->status === OrderStatus::ACCEPTED);
        $this->assertSame($driver->id, $order->driver_id);
        $this->assertSame($order->id, $driver->current_order_id);
    }

    public function test_claiming_broadcasts_order_taken_with_the_claiming_drivers_name(): void
    {
        Event::fake([OrderTaken::class]);

        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $this->actingAs($driver->user)->post(route('driver.orders.available.claim', $order));

        Event::assertDispatched(
            OrderTaken::class,
            fn ($event) => $event->orderId === $order->id && $event->driverName === $driver->user->name,
        );
    }

    public function test_a_second_driver_cannot_claim_an_order_already_claimed(): void
    {
        $winner = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $loser = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $this->actingAs($winner->user)->post(route('driver.orders.available.claim', $order));

        $this->actingAs($loser->user)
            ->post(route('driver.orders.available.claim', $order))
            ->assertRedirect() // back() with errors, not a 500
            ->assertSessionHasErrors('order');

        $loser->refresh();
        $this->assertNull($loser->current_order_id);
    }

    public function test_an_offline_driver_cannot_claim_an_available_order(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => false]);
        $order = Order::factory()->available()->create();

        $this->actingAs($driver->user)
            ->post(route('driver.orders.available.claim', $order))
            ->assertSessionHasErrors('order');

        $this->assertTrue($order->fresh()->status === OrderStatus::AVAILABLE);
    }

    public function test_an_already_occupied_driver_cannot_claim_another_order(): void
    {
        $current = Order::factory()->accepted()->create();
        $driver = Driver::factory()->create([
            'is_active' => true,
            'is_online' => true,
            'current_order_id' => $current->id,
        ]);
        $order = Order::factory()->available()->create();

        $this->actingAs($driver->user)
            ->post(route('driver.orders.available.claim', $order))
            ->assertSessionHasErrors('order');

        $this->assertTrue($order->fresh()->status === OrderStatus::AVAILABLE);
    }

    public function test_claiming_settles_the_claiming_drivers_own_pending_offer_as_accepted(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();
        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'result' => OrderOfferResult::PENDING,
        ]);

        $this->actingAs($driver->user)->post(route('driver.orders.available.claim', $order));

        $this->assertTrue($offer->fresh()->result === OrderOfferResult::ACCEPTED);
    }

    public function test_claiming_cancels_every_other_drivers_pending_offer_for_the_same_order(): void
    {
        $claimer = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $bystander = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();
        $bystanderOffer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $bystander->id,
            'result' => OrderOfferResult::PENDING,
        ]);

        $this->actingAs($claimer->user)->post(route('driver.orders.available.claim', $order));

        $this->assertTrue($bystanderOffer->fresh()->result === OrderOfferResult::CANCELLED);
    }
}
