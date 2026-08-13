<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
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
        // Delivered-today is the one cohort shared by Revenue/Driver
        // Earnings/Net Profit/Driver Overview below — built once so the
        // "today" boundary can't drift between them if this method is
        // ever split up later.
        $deliveredToday = Order::where('status', OrderStatus::DELIVERED->value)
            ->whereDate('delivered_at', today());

        $revenueToday = (float) (clone $deliveredToday)->sum('delivery_fee');
        $driverEarningsToday = (float) (clone $deliveredToday)->sum('driver_earning');
        $expensesToday = (float) Expense::today()->sum('amount');

        return view('admin.dashboard', [
            'user' => $request->user(),
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
            'totalOrdersToday' => Order::whereDate('created_at', today())->count(),
            'activeOrdersCount' => Order::active()->count(),
            'deliveredTodayCount' => (clone $deliveredToday)->count(),
            'revenueToday' => $revenueToday,
            'driverEarningsToday' => $driverEarningsToday,
            'expensesToday' => $expensesToday,
            'netProfitToday' => $revenueToday - $driverEarningsToday - $expensesToday,
            'recentOrders' => Order::with(['customer', 'driver.user'])
                ->latest()
                ->limit(self::RECENT_ORDERS_LIMIT)
                ->get(),
            'driverActivityToday' => $this->driverActivityToday(),
        ]);
    }

    /**
     * Per-driver delivered-today count + earnings — same "delivered
     * today" cohort as $deliveredToday above, just grouped by driver.
     * Only drivers with at least one delivery today are returned (an
     * empty result renders the card's existing empty-state, unchanged).
     *
     * @return Collection<int, Order>
     */
    private function driverActivityToday(): Collection
    {
        return Order::query()
            ->select('driver_id')
            ->selectRaw('COUNT(*) as delivered_count')
            ->selectRaw('SUM(driver_earning) as earnings_sum')
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereDate('delivered_at', today())
            ->whereNotNull('driver_id')
            ->groupBy('driver_id')
            ->with('driver.user')
            ->orderByDesc('delivered_count')
            ->get();
    }
}
