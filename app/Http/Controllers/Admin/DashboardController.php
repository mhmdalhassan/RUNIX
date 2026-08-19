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
    private const RECENT_ORDERS_LIMIT = 10;

    public function __invoke(Request $request): View
    {

        $period = DashboardPeriod::fromRequest($request->query('period'));
        $selectedDate = $this->parseSelectedDate($request->query('date'));

        if ($period->isCustom()) {
            $start = $selectedDate->copy()->startOfDay();
            $end = $selectedDate->copy()->endOfDay();
        } else {
            $start = $period->start();
            $end = Carbon::now();
        }

        $deliveredInRange = Order::where('status', OrderStatus::DELIVERED->value)
            ->whereBetween('delivered_at', [$start, $end]);

        $revenueToday = (float) (clone $deliveredInRange)->sum('delivery_fee');
        $driverEarningsToday = (float) (clone $deliveredInRange)->sum('driver_earning');
        $expensesToday = (float) Expense::betweenDates($start, $end)->sum('amount');

        return view('admin.dashboard', [
            'user' => $request->user(),
            'period' => $period,
            'periods' => DashboardPeriod::presets(),
            // Always populated (even outside CUSTOM) so the date-picker
            // input has a sane value to open on the first time it's used.
            'selectedDate' => $selectedDate,
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
     * Parses the `date` query param (CUSTOM's day picker) into a Carbon
     * date, falling back to today for a missing/malformed value — same
     * "never 500 the dashboard over a bad filter" spirit as
     * DashboardPeriod::fromRequest(). A future date is allowed through
     * unchanged (it just renders an empty day, same as any date with no
     * activity yet — not worth a special case).
     */
    private function parseSelectedDate(?string $value): Carbon
    {
        if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return today();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\InvalidArgumentException) {
            return today();
        }
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
