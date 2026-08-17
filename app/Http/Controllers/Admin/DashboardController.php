<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DashboardPeriod;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * How many rows the Recent Orders card shows — same limit
     * Dispatch\DashboardController uses for its own "recent" lists.
     */
    private const RECENT_ORDERS_LIMIT = 10;

    public function __invoke(Request $request): View
    {
        // Every reporting figure below is scoped to this range —
        // DashboardPeriod::TODAY (the default, when no ?period= is
        // given) reproduces the original always-today behavior exactly,
        // which is why the view keys stay *Today-suffixed regardless of
        // which period is actually selected.
        $period = DashboardPeriod::fromRequest($request->query('period'));
        $start = $period->start();
        $end = Carbon::now();

        // Delivered-in-range is the one cohort shared by Revenue/Driver
        // Earnings/Net Profit/Driver Overview below — built once so the
        // range boundaries can't drift between them if this method is
        // ever split up later.
        $deliveredInRange = Order::where('status', OrderStatus::DELIVERED->value)
            ->whereBetween('delivered_at', [$start, $end]);

        $revenueToday = (float) (clone $deliveredInRange)->sum('delivery_fee');
        $driverEarningsToday = (float) (clone $deliveredInRange)->sum('driver_earning');
        $expensesToday = (float) Expense::betweenDates($start, $end)->sum('amount');

        return view('admin.dashboard', [
            'user' => $request->user(),
            'period' => $period,
            'periods' => DashboardPeriod::cases(),
            'stats' => [
                'active_drivers' => Driver::where('is_active', true)->count(),
                'online_drivers' => Driver::where('is_online', true)->count(),
                'customers' => Customer::where('is_active', true)->count(),
                'staff' => User::staff()->count(),
            ],
            // Phase 8 — Admin Reporting Dashboard. Every order/driver
            // figure here reads data that already existed since Phase 3;
            // no new schema for those. Expenses (and therefore a real Net
            // Profit) came later, once Expense existed to back them —
            // see that model's own docblock.
            'totalOrdersToday' => Order::whereBetween('created_at', [$start, $end])->count(),
            'activeOrdersCount' => Order::active()->count(),
            'deliveredTodayCount' => (clone $deliveredInRange)->count(),
            'revenueToday' => $revenueToday,
            'driverEarningsToday' => $driverEarningsToday,
            'expensesToday' => $expensesToday,
            'netProfitToday' => $revenueToday - $driverEarningsToday - $expensesToday,
            'recentOrders' => Order::with(['customer', 'driver.user'])
                ->latest()
                ->limit(self::RECENT_ORDERS_LIMIT)
                ->get(),
            'driverActivityToday' => $this->driverActivityInRange($start, $end),
        ]);
    }

    /**
     * Per-driver delivered-in-range count + earnings — same
     * delivered-in-range cohort as $deliveredInRange above, just grouped
     * by driver. Only drivers with at least one delivery in range are
     * returned (an empty result renders the card's existing empty-state,
     * unchanged).
     *
     * @return Collection<int, Order>
     */
    private function driverActivityInRange(Carbon $start, Carbon $end): Collection
    {
        return Order::query()
            ->select('driver_id')
            ->selectRaw('COUNT(*) as delivered_count')
            ->selectRaw('SUM(driver_earning) as earnings_sum')
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereBetween('delivered_at', [$start, $end])
            ->whereNotNull('driver_id')
            ->groupBy('driver_id')
            ->with('driver.user')
            ->orderByDesc('delivered_count')
            ->get();
    }
}
