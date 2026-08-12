<?php

namespace Tests\Feature\Driver;

use App\Enums\OrderOfferResult;
use App\Enums\OrderStatus;
use App\Jobs\ExpireOrderOfferJob;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Notifications\NewOrderOfferNotification;
use App\Services\Orders\OfferOrderService;
use App\Services\Orders\RespondToOrderOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderOfferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    // --- Offer creation ---------------------------------------------------

    public function test_offering_an_available_order_creates_one_offer_per_eligible_driver(): void
    {
        Notification::fake();

        $drivers = Driver::factory()->count(2)->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $offers = app(OfferOrderService::class)->offer($order);

        $this->assertCount(2, $offers);
        $this->assertDatabaseCount('order_offers', 2);
        $this->assertSame(
            $drivers->pluck('id')->sort()->values()->all(),
            $offers->pluck('driver_id')->sort()->values()->all(),
        );
        foreach ($offers as $offer) {
            $this->assertSame($order->id, $offer->order_id);
            $this->assertTrue($offer->result === OrderOfferResult::PENDING);
        }
    }

    public function test_offering_a_non_available_order_is_a_no_op(): void
    {
        Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->create(); // pending, not available

        $offers = app(OfferOrderService::class)->offer($order);

        $this->assertCount(0, $offers);
        $this->assertDatabaseCount('order_offers', 0);
    }

    public function test_offering_twice_does_not_create_a_duplicate_pending_offer_for_the_same_driver(): void
    {
        Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $service = app(OfferOrderService::class);
        $service->offer($order);
        $service->offer($order);

        $this->assertDatabaseCount('order_offers', 1);
    }

    public function test_offering_dispatches_an_expiry_job_and_notifies_the_driver(): void
    {
        Notification::fake();

        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        app(OfferOrderService::class)->offer($order);

        Notification::assertSentTo($driver->user, NewOrderOfferNotification::class);
    }

    // --- Driver-facing views -----------------------------------------------

    public function test_driver_can_view_their_own_pending_offers(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();
        app(OfferOrderService::class)->offer($order);

        $this->actingAs($driver->user)->get(route('driver.offers.index'))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_driver_cannot_view_another_drivers_offer(): void
    {
        $driverA = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $driverB = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $driverA->id,
        ]);

        $this->actingAs($driverB->user)
            ->patch(route('driver.offers.accept', $offer))
            ->assertNotFound();

        $this->actingAs($driverB->user)
            ->patch(route('driver.offers.reject', $offer))
            ->assertNotFound();
    }

    // --- Accept -------------------------------------------------------------

    public function test_driver_can_accept_a_pending_offer(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();
        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);

        $this->actingAs($driver->user)
            ->patch(route('driver.offers.accept', $offer))
            ->assertRedirect(route('driver.dashboard'));

        $offer->refresh();
        $order->refresh();
        $driver->refresh();

        $this->assertTrue($offer->result === OrderOfferResult::ACCEPTED);
        $this->assertNotNull($offer->responded_at);
        $this->assertTrue($order->status === OrderStatus::ACCEPTED);
        $this->assertSame($driver->id, $order->driver_id);
        $this->assertSame($order->id, $driver->current_order_id);
    }

    public function test_accepting_an_offer_cancels_every_other_pending_offer_for_that_order(): void
    {
        $winner = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $loserA = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $loserB = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $winningOffer = OrderOffer::factory()->create(['order_id' => $order->id, 'driver_id' => $winner->id]);
        $loserOfferA = OrderOffer::factory()->create(['order_id' => $order->id, 'driver_id' => $loserA->id]);
        $loserOfferB = OrderOffer::factory()->create(['order_id' => $order->id, 'driver_id' => $loserB->id]);

        $this->actingAs($winner->user)->patch(route('driver.offers.accept', $winningOffer))->assertRedirect();

        $this->assertTrue($winningOffer->fresh()->result === OrderOfferResult::ACCEPTED);
        $this->assertTrue($loserOfferA->fresh()->result === OrderOfferResult::CANCELLED);
        $this->assertTrue($loserOfferB->fresh()->result === OrderOfferResult::CANCELLED);
        $this->assertNull($loserA->fresh()->current_order_id);
        $this->assertNull($loserB->fresh()->current_order_id);
    }

    public function test_accepting_an_already_responded_offer_fails_gracefully(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();
        $offer = OrderOffer::factory()->rejected()->create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);

        $this->actingAs($driver->user)
            ->patch(route('driver.offers.accept', $offer))
            ->assertSessionHasErrors('offer');

        $this->assertTrue($order->fresh()->status === OrderStatus::AVAILABLE);
    }

    public function test_accepting_an_offer_for_an_order_someone_else_already_claimed_fails_gracefully_and_cancels_the_offer(): void
    {
        $winner = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $loser = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $winningOffer = OrderOffer::factory()->create(['order_id' => $order->id, 'driver_id' => $winner->id]);
        $losingOffer = OrderOffer::factory()->create(['order_id' => $order->id, 'driver_id' => $loser->id]);

        // Winner claims first (simulating it having already happened).
        app(RespondToOrderOfferService::class)->accept($winningOffer, $winner->user);

        $this->actingAs($loser->user)
            ->patch(route('driver.offers.accept', $losingOffer))
            ->assertSessionHasErrors('offer');

        $this->assertTrue($losingOffer->fresh()->result === OrderOfferResult::CANCELLED);
        $this->assertNull($loser->fresh()->current_order_id);
    }

    // --- Reject -------------------------------------------------------------

    public function test_driver_can_reject_a_pending_offer(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();
        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);

        $this->actingAs($driver->user)
            ->patch(route('driver.offers.reject', $offer))
            ->assertRedirect();

        $offer->refresh();
        $this->assertTrue($offer->result === OrderOfferResult::REJECTED);
        $this->assertNotNull($offer->responded_at);
        $this->assertTrue($order->fresh()->status === OrderStatus::AVAILABLE);
        $this->assertNull($driver->fresh()->current_order_id);
    }

    public function test_rejecting_does_not_affect_other_pending_offers_for_the_same_order(): void
    {
        $driverA = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $driverB = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $offerA = OrderOffer::factory()->create(['order_id' => $order->id, 'driver_id' => $driverA->id]);
        $offerB = OrderOffer::factory()->create(['order_id' => $order->id, 'driver_id' => $driverB->id]);

        $this->actingAs($driverA->user)->patch(route('driver.offers.reject', $offerA))->assertRedirect();

        $this->assertTrue($offerB->fresh()->result === OrderOfferResult::PENDING);
    }

    // --- Expiry ---------------------------------------------------------------

    public function test_expire_job_marks_a_stale_pending_offer_as_expired(): void
    {
        Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();
        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'offered_at' => now()->subMinutes(3),
        ]);

        (new ExpireOrderOfferJob($offer->id))->handle(app(OfferOrderService::class));

        $this->assertTrue($offer->fresh()->result === OrderOfferResult::EXPIRED);
        $this->assertNotNull($offer->fresh()->responded_at);
    }

    public function test_expire_job_does_nothing_before_the_real_timeout_has_elapsed(): void
    {
        $offer = OrderOffer::factory()->create(['offered_at' => now()]);

        (new ExpireOrderOfferJob($offer->id))->handle(app(OfferOrderService::class));

        $this->assertTrue($offer->fresh()->result === OrderOfferResult::PENDING);
    }

    public function test_expire_job_re_offers_to_currently_eligible_drivers_when_the_order_is_still_available(): void
    {
        $originalDriver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $newlyEligibleDriver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->available()->create();

        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $originalDriver->id,
            'offered_at' => now()->subMinutes(3),
        ]);

        (new ExpireOrderOfferJob($offer->id))->handle(app(OfferOrderService::class));

        $this->assertTrue($offer->fresh()->result === OrderOfferResult::EXPIRED);

        // A fresh round went out to every currently-eligible driver,
        // including the one who wasn't around for the first round.
        $freshPending = OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('result', OrderOfferResult::PENDING->value)
            ->pluck('driver_id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            [$originalDriver->id, $newlyEligibleDriver->id],
            $freshPending,
        );
    }

    public function test_expire_job_does_not_re_offer_if_the_order_is_no_longer_available(): void
    {
        Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->accepted()->create();
        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'offered_at' => now()->subMinutes(3),
        ]);

        (new ExpireOrderOfferJob($offer->id))->handle(app(OfferOrderService::class));

        $this->assertTrue($offer->fresh()->result === OrderOfferResult::EXPIRED);
        $this->assertDatabaseCount('order_offers', 1);
    }
}
