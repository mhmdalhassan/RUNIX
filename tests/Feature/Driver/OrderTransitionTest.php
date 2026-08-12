<?php

namespace Tests\Feature\Driver;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Driver-facing status transitions reuse the exact same
 * OrderTransitionService/matrix the admin UI uses (spec §9) — these tests
 * exercise driver-scoped access to that matrix, not a second copy of it.
 */
class OrderTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function occupiedDriverWithOrder(OrderStatus $status = OrderStatus::ACCEPTED): array
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $order = Order::factory()->accepted()->create(['driver_id' => $driver->id]);
        $driver->update(['current_order_id' => $order->id]);

        if ($status !== OrderStatus::ACCEPTED) {
            // status isn't mass-assignable (not in Order's #[Fillable]) —
            // direct property assignment bypasses that guard the same way
            // OrderTransitionService itself does.
            $order->status = $status;
            $order->save();
        }

        return [$driver, $order];
    }

    public function test_driver_can_progress_their_own_order_through_the_full_matrix(): void
    {
        [$driver, $order] = $this->occupiedDriverWithOrder();

        $this->actingAs($driver->user)
            ->patch(route('driver.orders.transition', $order), ['to_status' => 'picked_up'])
            ->assertRedirect(route('driver.orders.show', $order));
        $this->assertTrue($order->fresh()->status === OrderStatus::PICKED_UP);

        $this->actingAs($driver->user)
            ->patch(route('driver.orders.transition', $order), ['to_status' => 'on_the_way'])
            ->assertRedirect();
        $this->assertTrue($order->fresh()->status === OrderStatus::ON_THE_WAY);

        $this->actingAs($driver->user)
            ->patch(route('driver.orders.transition', $order), ['to_status' => 'delivered'])
            ->assertRedirect();
        $this->assertTrue($order->fresh()->status === OrderStatus::DELIVERED);
    }

    public function test_driver_cannot_skip_a_status_in_the_matrix(): void
    {
        [$driver, $order] = $this->occupiedDriverWithOrder(); // ACCEPTED

        $this->actingAs($driver->user)
            ->patch(route('driver.orders.transition', $order), ['to_status' => 'delivered'])
            ->assertSessionHasErrors('to_status');

        $this->assertTrue($order->fresh()->status === OrderStatus::ACCEPTED);
    }

    public function test_driver_can_cancel_their_own_order(): void
    {
        [$driver, $order] = $this->occupiedDriverWithOrder();

        $this->actingAs($driver->user)
            ->patch(route('driver.orders.transition', $order), ['to_status' => 'cancelled'])
            ->assertRedirect();

        $this->assertTrue($order->fresh()->status === OrderStatus::CANCELLED);
    }

    public function test_driver_can_mark_their_order_failed(): void
    {
        [$driver, $order] = $this->occupiedDriverWithOrder();

        $this->actingAs($driver->user)
            ->patch(route('driver.orders.transition', $order), ['to_status' => 'failed'])
            ->assertRedirect();

        $this->assertTrue($order->fresh()->status === OrderStatus::FAILED);
    }

    public function test_reaching_a_terminal_status_releases_the_driver(): void
    {
        [$driver, $order] = $this->occupiedDriverWithOrder(OrderStatus::ON_THE_WAY);

        $this->assertSame($order->id, $driver->fresh()->current_order_id);

        $this->actingAs($driver->user)
            ->patch(route('driver.orders.transition', $order), ['to_status' => 'delivered'])
            ->assertRedirect();

        $this->assertNull($driver->fresh()->current_order_id);
    }

    public function test_driver_cannot_transition_an_order_that_is_not_theirs(): void
    {
        [$owner, $order] = $this->occupiedDriverWithOrder();
        $otherDriver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);

        $this->actingAs($otherDriver->user)
            ->patch(route('driver.orders.transition', $order), ['to_status' => 'picked_up'])
            ->assertNotFound();

        $this->assertTrue($order->fresh()->status === OrderStatus::ACCEPTED);
    }

    public function test_driver_cannot_view_an_order_that_is_not_theirs(): void
    {
        [$owner, $order] = $this->occupiedDriverWithOrder();
        $otherDriver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);

        $this->actingAs($otherDriver->user)
            ->get(route('driver.orders.show', $order))
            ->assertNotFound();
    }

    public function test_driver_cannot_transition_an_order_that_has_no_driver_assigned_yet(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $available = Order::factory()->available()->create();

        // Not their order (driver_id is null) — the scoped relation query
        // 404s before any transition logic even runs.
        $this->actingAs($driver->user)
            ->patch(route('driver.orders.transition', $available), ['to_status' => 'accepted'])
            ->assertNotFound();
    }

    public function test_driver_cannot_transition_a_terminal_order_further(): void
    {
        [$driver, $order] = $this->occupiedDriverWithOrder(OrderStatus::DELIVERED);

        $this->actingAs($driver->user)
            ->patch(route('driver.orders.transition', $order), ['to_status' => 'cancelled'])
            ->assertSessionHasErrors('to_status');
    }
}
