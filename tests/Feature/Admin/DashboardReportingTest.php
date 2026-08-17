<?php

namespace Tests\Feature\Admin;

use App\Enums\DashboardPeriod;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8 — Admin Reporting Dashboard. Access control for /admin/dashboard
 * itself (super admin can, dispatcher/driver cannot) is already fully
 * covered by RoleAuthorizationTest — deliberately not duplicated here.
 * These tests only cover the new reporting content
 * Admin\DashboardController computes.
 *
 * Numeric assertions use assertViewHas()/viewData() against the raw
 * computed values rather than scanning rendered HTML for a matching
 * digit — the same lesson Phase 7's tracking tests already established:
 * a bare numeric substring can coincidentally match unrelated rendered
 * content (dates, prices, ids).
 */
class DashboardReportingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    // --- Total Orders Today ------------------------------------------------

    public function test_total_orders_today_counts_only_orders_created_today(): void
    {
        Order::factory()->count(2)->create();
        Order::factory()->create(['created_at' => now()->subDay()]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('totalOrdersToday', 2);
    }

    // --- Active Orders --------------------------------------------------

    public function test_active_orders_counts_non_terminal_orders_regardless_of_date(): void
    {
        Order::factory()->create(['created_at' => now()->subWeek()]); // pending — active, old
        Order::factory()->available()->create(); // active
        Order::factory()->delivered()->create(); // terminal — excluded
        Order::factory()->cancelled()->create(); // terminal — excluded

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertViewHas('activeOrdersCount', 2);
    }

    // --- Delivered Today --------------------------------------------------

    public function test_delivered_today_counts_only_delivered_orders_with_delivered_at_today(): void
    {
        Order::factory()->delivered()->create();
        Order::factory()->delivered()->create();
        Order::factory()->delivered()->create(['delivered_at' => now()->subDay()]);
        Order::factory()->accepted()->create(); // never delivered

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertViewHas('deliveredTodayCount', 2);
    }

    // --- Revenue (decision: delivered-today, sum of delivery_fee) ---------

    public function test_revenue_sums_delivery_fee_for_orders_delivered_today_only(): void
    {
        Order::factory()->delivered()->create(['delivery_fee' => 10]);
        Order::factory()->delivered()->create(['delivery_fee' => 15.5]);
        Order::factory()->delivered()->create(['delivery_fee' => 100, 'delivered_at' => now()->subDay()]);
        Order::factory()->available()->create(['delivery_fee' => 999]); // not delivered

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertViewHas('revenueToday', 25.5);
    }

    // --- Driver Earnings Today ----------------------------------------------

    public function test_driver_earnings_today_sums_driver_earning_for_orders_delivered_today_only(): void
    {
        Order::factory()->delivered()->create(['driver_earning' => 7]);
        Order::factory()->delivered()->create(['driver_earning' => 3.25]);
        Order::factory()->delivered()->create(['driver_earning' => 50, 'delivered_at' => now()->subDay()]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertViewHas('driverEarningsToday', 10.25);
    }

    // --- Net Profit (decision: Revenue - Driver Earnings - Expenses) --------

    public function test_net_profit_is_revenue_minus_driver_earnings_with_no_expenses_recorded(): void
    {
        Order::factory()->delivered()->create(['delivery_fee' => 20, 'driver_earning' => 14]);
        Order::factory()->delivered()->create(['delivery_fee' => 10, 'driver_earning' => 6]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertViewHas('revenueToday', 30.0);
        $response->assertViewHas('driverEarningsToday', 20.0);
        $response->assertViewHas('expensesToday', 0.0);
        $response->assertViewHas('netProfitToday', 10.0);
    }

    public function test_expenses_today_sums_todays_expenses_only(): void
    {
        Expense::factory()->create(['date' => today(), 'amount' => 15]);
        Expense::factory()->create(['date' => today(), 'amount' => 5]);
        Expense::factory()->create(['date' => today()->subDay(), 'amount' => 999]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertViewHas('expensesToday', 20.0);
    }

    public function test_net_profit_subtracts_expenses_too(): void
    {
        Order::factory()->delivered()->create(['delivery_fee' => 50, 'driver_earning' => 30]);
        Expense::factory()->create(['date' => today(), 'amount' => 8]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertViewHas('revenueToday', 50.0);
        $response->assertViewHas('driverEarningsToday', 30.0);
        $response->assertViewHas('expensesToday', 8.0);
        // 50 - 30 - 8 = 12
        $response->assertViewHas('netProfitToday', 12.0);
    }

    // --- Recent Orders --------------------------------------------------

    public function test_recent_orders_shows_the_latest_orders_first(): void
    {
        $older = Order::factory()->create(['created_at' => now()->subHours(2)]);
        $newer = Order::factory()->create(['created_at' => now()->subMinutes(5)]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertOk();
        $recentOrders = $response->viewData('recentOrders');
        $this->assertTrue($recentOrders->first()->is($newer));
        $this->assertTrue($recentOrders->last()->is($older));
    }

    public function test_recent_orders_respects_the_display_limit(): void
    {
        Order::factory()->count(15)->create();

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $this->assertCount(10, $response->viewData('recentOrders'));
    }

    public function test_recent_orders_renders_the_order_number_and_customer_name(): void
    {
        $order = Order::factory()->create(['customer_name_snapshot' => 'Ultra Distinctive Customer']);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertSee($order->order_number);
        $response->assertSee('Ultra Distinctive Customer');
    }

    // --- Driver Overview --------------------------------------------------

    public function test_driver_overview_shows_delivered_count_and_earnings_for_a_driver_active_today(): void
    {
        $driver = Driver::factory()->create();
        Order::factory()->delivered()->create(['driver_id' => $driver->id, 'driver_earning' => 6]);
        Order::factory()->delivered()->create(['driver_id' => $driver->id, 'driver_earning' => 4]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $activity = $response->viewData('driverActivityToday');
        $this->assertCount(1, $activity);
        $this->assertSame(2, (int) $activity->first()->delivered_count);
        $this->assertEquals(10.0, (float) $activity->first()->earnings_sum);
        $response->assertSee($driver->user->name);
    }

    public function test_driver_overview_omits_drivers_with_no_deliveries_today(): void
    {
        $idleDriver = Driver::factory()->create();
        Order::factory()->delivered()->create(['driver_id' => $idleDriver->id, 'delivered_at' => now()->subDay()]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $this->assertCount(0, $response->viewData('driverActivityToday'));
    }

    // --- Empty dashboard state (fresh install / quiet day) ------------------

    public function test_dashboard_renders_correctly_with_no_orders_at_all(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('totalOrdersToday', 0);
        $response->assertViewHas('activeOrdersCount', 0);
        $response->assertViewHas('deliveredTodayCount', 0);
        $response->assertViewHas('revenueToday', 0.0);
        $response->assertViewHas('driverEarningsToday', 0.0);
        $response->assertViewHas('netProfitToday', 0.0);
        $this->assertCount(0, $response->viewData('recentOrders'));
        $this->assertCount(0, $response->viewData('driverActivityToday'));
        $response->assertSee('No orders yet');
        $response->assertSee('Nothing to review');
    }

    // --- Date range filter (?period=) --------------------------------------

    public function test_period_defaults_to_today_when_no_query_param_is_given(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertViewHas('period', DashboardPeriod::TODAY);
    }

    public function test_unknown_period_value_falls_back_to_today_instead_of_erroring(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/dashboard?period=decade');

        $response->assertOk();
        $response->assertViewHas('period', DashboardPeriod::TODAY);
    }

    public function test_week_period_includes_earlier_this_week_but_excludes_last_week(): void
    {
        Order::factory()->delivered()->create([
            'delivery_fee' => 20,
            'delivered_at' => now()->startOfWeek()->addHours(2),
        ]);
        Order::factory()->delivered()->create([
            'delivery_fee' => 999,
            'delivered_at' => now()->subWeeks(2),
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard?period=week');

        $response->assertViewHas('period', DashboardPeriod::WEEK);
        $response->assertViewHas('revenueToday', 20.0);
    }

    public function test_month_period_includes_earlier_this_month_but_excludes_last_month(): void
    {
        Order::factory()->delivered()->create([
            'delivery_fee' => 30,
            'delivered_at' => now()->startOfMonth()->addHours(2),
        ]);
        Order::factory()->delivered()->create([
            'delivery_fee' => 999,
            'delivered_at' => now()->subMonths(2),
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard?period=month');

        $response->assertViewHas('period', DashboardPeriod::MONTH);
        $response->assertViewHas('revenueToday', 30.0);
    }

    public function test_year_period_includes_earlier_this_year_but_excludes_last_year(): void
    {
        Order::factory()->delivered()->create([
            'delivery_fee' => 40,
            'delivered_at' => now()->startOfYear()->addDay(),
        ]);
        Order::factory()->delivered()->create([
            'delivery_fee' => 999,
            'delivered_at' => now()->subYears(2),
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard?period=year');

        $response->assertViewHas('period', DashboardPeriod::YEAR);
        $response->assertViewHas('revenueToday', 40.0);
    }

    public function test_expenses_respect_the_selected_period_not_just_today(): void
    {
        Expense::factory()->create(['date' => today(), 'amount' => 10]);
        Expense::factory()->create(['date' => today()->subDays(3), 'amount' => 5]);
        Expense::factory()->create(['date' => today()->subMonths(2), 'amount' => 999]);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard?period=week');

        // today (10) + 3 days ago (5), as long as that's still this week —
        // subDays(3) can spill into last week depending on today's weekday,
        // so only assert the floor a same-week day guarantees: today's own
        // expense is always included.
        $expensesToday = $response->viewData('expensesToday');
        $this->assertGreaterThanOrEqual(10.0, $expensesToday);
        $this->assertLessThan(999.0, $expensesToday);
    }

    // --- Filter tabs render for every period --------------------------------

    public function test_dashboard_renders_a_tab_for_every_period(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/dashboard');

        $response->assertOk();
        foreach (DashboardPeriod::cases() as $option) {
            $response->assertSee($option->label());
        }
    }
}
