<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\DriverFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a driver's feedback is actually surfaced: their own dashboard,
 * and the dispatcher/super_admin-facing driver profile — see docs/
 * DASHBOARD.md. Never anywhere else (other drivers, customers, public).
 */
class DriverFeedbackVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_driver_sees_their_own_average_rating_and_feedback_count_on_their_dashboard(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        DriverFeedback::factory()->create(['driver_id' => $driver->id, 'rating' => 5]);
        DriverFeedback::factory()->create(['driver_id' => $driver->id, 'rating' => 3, 'comment' => 'Order arrived cold.']);

        $this->actingAs($driver->user)
            ->get(route('driver.dashboard'))
            ->assertOk()
            ->assertSee('4.0') // (5 + 3) / 2
            ->assertSee('Order arrived cold.')
            ->assertSee('2 reviews');
    }

    public function test_a_driver_with_no_feedback_sees_a_no_ratings_state(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);

        $this->actingAs($driver->user)
            ->get(route('driver.dashboard'))
            ->assertOk()
            ->assertSee(__('No ratings yet'));
    }

    public function test_a_driver_never_sees_another_drivers_feedback_on_their_own_dashboard(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $otherDriver = Driver::factory()->create();
        DriverFeedback::factory()->create(['driver_id' => $otherDriver->id, 'comment' => 'Ultra Secret Other Drivers Feedback']);

        $this->actingAs($driver->user)
            ->get(route('driver.dashboard'))
            ->assertOk()
            ->assertDontSee('Ultra Secret Other Drivers Feedback')
            ->assertSee(__('No ratings yet'));
    }

    public function test_a_dispatcher_sees_a_drivers_feedback_on_the_admin_profile_page(): void
    {
        $dispatcher = User::factory()->create(['role' => UserRole::DISPATCHER]);
        $driver = Driver::factory()->create();
        DriverFeedback::factory()->create(['driver_id' => $driver->id, 'rating' => 4, 'comment' => 'Friendly and on time.']);

        $this->actingAs($dispatcher)
            ->get(route('admin.drivers.show', $driver))
            ->assertOk()
            ->assertSee('4.0')
            ->assertSee('Friendly and on time.')
            ->assertSee('1 review');
    }

    public function test_a_driver_cannot_view_another_drivers_admin_profile_page(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $otherDriver = Driver::factory()->create();

        $this->actingAs($driver->user)
            ->get(route('admin.drivers.show', $otherDriver))
            ->assertForbidden();
    }
}
