<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Driver::deliveryHistoryQuery() — per-day delivered-order count +
 * earnings, shown on both the driver's own dashboard and the admin
 * driver detail page (resources/views/components/driver-delivery-history.blade.php).
 * Read-only reporting over existing data; not the deferred earnings
 * ledger/settlement work, which tracks paid/unpaid — this never does.
 */
class DriverDeliveryHistoryTest extends TestCase
{
    use RefreshDatabase;

    // --- The query itself -------------------------------------------------

    public function test_multiple_deliveries_on_the_same_day_are_grouped_into_one_row(): void
    {
        $driver = Driver::factory()->create();
        Order::factory()->delivered()->create(['driver_id' => $driver->id, 'driver_earning' => 6]);
        Order::factory()->delivered()->create(['driver_id' => $driver->id, 'driver_earning' => 4]);

        $history = $driver->deliveryHistoryQuery()->get();

        $this->assertCount(1, $history);
        $this->assertSame(2, (int) $history->first()->delivered_count);
        $this->assertEquals(10.0, (float) $history->first()->earnings_sum);
    }

    public function test_deliveries_on_different_days_produce_separate_rows_newest_first(): void
    {
        $driver = Driver::factory()->create();
        Order::factory()->delivered()->create(['driver_id' => $driver->id, 'delivered_at' => now()->subDays(2)]);
        Order::factory()->delivered()->create(['driver_id' => $driver->id, 'delivered_at' => now()]);

        $history = $driver->deliveryHistoryQuery()->get();

        $this->assertCount(2, $history);
        // Newest day first.
        $this->assertSame(now()->toDateString(), (string) $history->first()->delivery_date);
        $this->assertSame(now()->subDays(2)->toDateString(), (string) $history->last()->delivery_date);
    }

    public function test_only_delivered_orders_count_toward_history(): void
    {
        $driver = Driver::factory()->create();
        Order::factory()->delivered()->create(['driver_id' => $driver->id]);
        Order::factory()->accepted()->create(['driver_id' => $driver->id]);
        Order::factory()->cancelled()->create(['driver_id' => $driver->id]);

        $history = $driver->deliveryHistoryQuery()->get();

        $this->assertCount(1, $history);
        $this->assertSame(1, (int) $history->first()->delivered_count);
    }

    public function test_another_drivers_deliveries_never_leak_into_this_history(): void
    {
        $driver = Driver::factory()->create();
        $otherDriver = Driver::factory()->create();
        Order::factory()->delivered()->create(['driver_id' => $driver->id]);
        Order::factory()->delivered()->create(['driver_id' => $otherDriver->id]);

        $history = $driver->deliveryHistoryQuery()->get();

        $this->assertCount(1, $history);
        $this->assertSame(1, (int) $history->first()->delivered_count);
    }

    // --- Driver's own dashboard ---------------------------------------------

    public function test_driver_sees_their_own_delivery_history_on_the_dashboard(): void
    {
        $driverUser = User::factory()->driver()->create();
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
        Order::factory()->delivered()->create(['driver_id' => $driver->id, 'driver_earning' => 12.5]);

        $response = $this->actingAs($driverUser)->get('/driver/dashboard');

        $response->assertOk();
        $response->assertSee('Delivery History');
        $response->assertSee(now()->format('M j, Y'));
        $response->assertSee('12.50');
    }

    public function test_driver_dashboard_shows_empty_state_with_no_deliveries_yet(): void
    {
        $driverUser = User::factory()->driver()->create();
        Driver::factory()->create(['user_id' => $driverUser->id]);

        $response = $this->actingAs($driverUser)->get('/driver/dashboard');

        $response->assertOk();
        $response->assertSee('No delivery history yet');
    }

    /**
     * A driver-role user with no linked Driver record at all (an existing,
     * already-handled edge case elsewhere on this page) must not crash
     * when the new history card renders.
     */
    public function test_driver_dashboard_does_not_crash_with_no_driver_profile_linked(): void
    {
        $driverUser = User::factory()->driver()->create();

        $response = $this->actingAs($driverUser)->get('/driver/dashboard');

        $response->assertOk();
        $response->assertSee('No driver profile linked');
    }

    // --- Admin driver detail page -----------------------------------------

    public function test_admin_sees_a_drivers_delivery_history_on_the_driver_detail_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $driver = Driver::factory()->create();
        Order::factory()->delivered()->create(['driver_id' => $driver->id, 'driver_earning' => 7.25]);

        $response = $this->actingAs($admin)->get(route('admin.drivers.show', $driver));

        $response->assertOk();
        $response->assertSee('Delivery History');
        $response->assertSee('7.25');
    }

    public function test_dispatcher_sees_a_drivers_delivery_history_too(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $driver = Driver::factory()->create();
        Order::factory()->delivered()->create(['driver_id' => $driver->id]);

        $response = $this->actingAs($dispatcher)->get(route('admin.drivers.show', $driver));

        $response->assertOk();
        $response->assertSee('Delivery History');
    }

    // --- Pagination (all-time, unbounded — spec confirmed) -----------------

    public function test_delivery_history_paginates_at_fifteen_days_per_page(): void
    {
        $driver = Driver::factory()->create();

        foreach (range(0, 19) as $daysAgo) {
            Order::factory()->delivered()->create([
                'driver_id' => $driver->id,
                'delivered_at' => now()->subDays($daysAgo),
            ]);
        }

        $paginator = $driver->deliveryHistoryQuery()->paginate(15);

        $this->assertSame(20, $paginator->total());
        $this->assertCount(15, $paginator->items());
        $this->assertTrue($paginator->hasPages());
    }
}
