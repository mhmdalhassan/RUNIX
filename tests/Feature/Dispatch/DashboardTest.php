<?php

namespace Tests\Feature\Dispatch;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the ?partial=1 fragment resources/js/runix/dispatch-dashboard.js
 * polls/refreshes on a realtime hint — same authorization as the full
 * page (DashboardController runs Gate::authorize('viewAny', Driver::class)
 * before branching on the partial flag), so this is really a test that
 * the live-refresh endpoint can't be used to see dispatcher-only data
 * through a side door.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dispatcher_can_load_the_partial_board(): void
    {
        $dispatcher = User::factory()->create(['role' => UserRole::DISPATCHER]);
        // Old enough to also show up in "Orders Needing Attention" (>5
        // minutes AVAILABLE), so its order_number is guaranteed to render
        // somewhere on the board regardless of activity-feed history.
        $order = Order::factory()->available()->create(['created_at' => now()->subMinutes(10)]);

        $this->actingAs($dispatcher)
            ->get(route('dispatch.dashboard', ['partial' => 1]))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertDontSee('<!DOCTYPE html>', false); // fragment only, no surrounding layout
    }

    public function test_a_super_admin_can_load_the_partial_board(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->get(route('dispatch.dashboard', ['partial' => 1]))
            ->assertOk();
    }

    public function test_a_driver_cannot_load_the_partial_board(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);

        $this->actingAs($driver->user)
            ->get(route('dispatch.dashboard', ['partial' => 1]))
            ->assertForbidden();
    }

    public function test_a_restaurant_admin_cannot_load_the_partial_board(): void
    {
        $restaurantAdmin = User::factory()->create(['role' => UserRole::RESTAURANT_ADMIN]);

        $this->actingAs($restaurantAdmin)
            ->get(route('dispatch.dashboard', ['partial' => 1]))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('dispatch.dashboard', ['partial' => 1]))
            ->assertRedirect(route('login'));
    }

    public function test_the_partial_reflects_a_newly_available_order(): void
    {
        $dispatcher = User::factory()->create(['role' => UserRole::DISPATCHER]);

        $this->actingAs($dispatcher)
            ->get(route('dispatch.dashboard', ['partial' => 1]))
            ->assertOk()
            ->assertDontSee('RUN-'); // no orders yet, so no order number should render

        $order = Order::factory()->available()->create(['created_at' => now()->subMinutes(10)]);

        $this->actingAs($dispatcher)
            ->get(route('dispatch.dashboard', ['partial' => 1]))
            ->assertOk()
            ->assertSee($order->order_number);
    }
}
