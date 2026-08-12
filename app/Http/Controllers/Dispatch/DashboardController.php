<?php

namespace App\Http\Controllers\Dispatch;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Driver::class);

        return view('dispatch.dashboard', [
            'user' => $request->user(),
            'drivers' => Driver::with('user')->latest()->get(),
            // Real now that Orders exists — see the view's own comment
            // for why "Available Drivers"/"Delivering Now" were already
            // real before this phase.
            'availableOrdersCount' => Order::where('status', OrderStatus::AVAILABLE->value)->count(),
            'activeDeliveriesCount' => Order::whereIn('status', [
                OrderStatus::ACCEPTED->value,
                OrderStatus::PICKED_UP->value,
                OrderStatus::ON_THE_WAY->value,
            ])->count(),
            'ordersNeedingAttention' => Order::with('driver.user')
                ->where('status', OrderStatus::AVAILABLE->value)
                ->where('created_at', '<=', now()->subMinutes(5))
                ->latest()
                ->limit(10)
                ->get(),
            'recentActivity' => OrderStatusHistory::with(['order', 'changedBy'])
                ->latest('created_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
