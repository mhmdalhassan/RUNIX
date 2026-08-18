<?php

namespace Tests\Feature\Driver;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A driver backing out of an order they've claimed, before pickup —
 * App\Services\Orders\ReleaseOrderForDriverService. Distinct from the
 * ordinary CANCELLED transition covered by OrderTransitionTest: this one
 * returns the order to AVAILABLE (immediately claimable by anyone else,
 * same as any other route to AVAILABLE — see OfferOrderService) instead
 * of ending it.
 */
class OrderReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function occupiedDriverWithOrder(OrderStatus $status = OrderStatus::ACCEPTED): array
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->accepted()->create(['driver_id' => $driver->id]);
        $driver->update(['current_order_id' => $order->id]);

        if ($status !== OrderStatus::ACCEPTED) {
            $order->status = $status;
            $order->save();
        }

        return [$driver, $order];
    }

    public function test_driver_can_release_an_accepted_order_back_to_available(): void
    {
        Queue::fake();
        [$driver, $order] = $this->occupiedDriverWithOrder();

        $this->actingAs($driver->user)
            ->patch(route('driver.orders.release', $order))
            ->assertRedirect(route('driver.orders.available'));

        $order->refresh();
        $this->assertSame(OrderStatus::AVAILABLE, $order->status);
        $this->assertNull($order->driver_id);
        $this->assertNull($order->assigned_at);
        $this->assertNull($order->accepted_at);
        $this->assertNull($driver->fresh()->current_order_id);
    }

    public function test_a_released_order_appears_on_the_available_orders_board(): void
    {
        Queue::fake();
        [$driver, $order] = $this->occupiedDriverWithOrder();

        $this->actingAs($driver->user)->patch(route('driver.orders.release', $order));

        $this->actingAs($driver->user)
            ->get(route('driver.orders.available'))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_releasing_an_order_offers_it_to_other_eligible_drivers(): void
    {
        Queue::fake();
        [$driver, $order] = $this->occupiedDriverWithOrder();
        $otherDriver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);

        $this->actingAs($driver->user)->patch(route('driver.orders.release', $order));

        $this->assertTrue(
            OrderOffer::where('order_id', $order->id)->where('driver_id', $otherDriver->id)->exists()
        );
    }

    public function test_a_released_orders_history_records_the_hop_back_to_available(): void
    {
        Queue::fake();
        [$driver, $order] = $this->occupiedDriverWithOrder();

        $this->actingAs($driver->user)->patch(route('driver.orders.release', $order));

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::ACCEPTED->value,
            'to_status' => OrderStatus::AVAILABLE->value,
            'changed_by' => $driver->user->id,
        ]);
    }

    public function test_driver_cannot_release_an_order_that_is_not_theirs(): void
    {
        [$owner, $order] = $this->occupiedDriverWithOrder();
        $otherDriver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);

        $this->actingAs($otherDriver->user)
            ->patch(route('driver.orders.release', $order))
            ->assertNotFound();

        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
    }

    public function test_driver_cannot_release_an_order_that_has_already_been_picked_up(): void
    {
        [$driver, $order] = $this->occupiedDriverWithOrder(OrderStatus::PICKED_UP);

        $this->actingAs($driver->user)
            ->patch(route('driver.orders.release', $order))
            ->assertSessionHasErrors('order');

        $this->assertSame(OrderStatus::PICKED_UP, $order->fresh()->status);
        $this->assertSame($driver->id, $order->fresh()->driver_id);
    }

    public function test_driver_cannot_release_a_terminal_order(): void
    {
        [$driver, $order] = $this->occupiedDriverWithOrder(OrderStatus::DELIVERED);

        $this->actingAs($driver->user)
            ->patch(route('driver.orders.release', $order))
            ->assertSessionHasErrors('order');
    }

    public function test_the_cancel_button_shown_to_the_driver_releases_rather_than_terminally_cancels_while_accepted(): void
    {
        [$driver, $order] = $this->occupiedDriverWithOrder();

        // The release route's form is rendered, and there's no
        // to_status=cancelled hidden input left over from the generic
        // transition loop (Picked Up/Failed still use that route/pattern
        // for their own buttons, so asserting the route itself is absent
        // would be a false negative).
        $this->actingAs($driver->user)
            ->get(route('driver.orders.show', $order))
            ->assertOk()
            ->assertSee('action="'.route('driver.orders.release', $order).'"', false)
            ->assertDontSee('value="cancelled"', false);
    }

    public function test_the_ordinary_cancel_transition_is_used_once_picked_up(): void
    {
        [$driver, $order] = $this->occupiedDriverWithOrder(OrderStatus::PICKED_UP);

        $this->actingAs($driver->user)
            ->get(route('driver.orders.show', $order))
            ->assertOk()
            ->assertSee('value="cancelled"', false);
    }
}
