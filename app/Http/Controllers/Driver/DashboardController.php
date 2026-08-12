<?php

namespace App\Http\Controllers\Driver;

use App\Enums\OrderOfferResult;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $driver = $request->user()->driver;

        // Real now that Orders/driver_earning exist — a simple "what did
        // I do today" display sum, not a ledger/settlement calculation
        // (still out of scope, see spec §18). Two separate queries rather
        // than reusing one builder instance across count()/sum().
        $todaysDeliveredQuery = fn () => $driver
            ->orders()
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereDate('delivered_at', today());

        return view('driver.dashboard', [
            'user' => $request->user(),
            'driver' => $driver,
            'currentOrder' => $driver?->currentOrder,
            'offers' => $driver
                ? $driver->offers()->with('order')->where('result', OrderOfferResult::PENDING->value)->latest('offered_at')->get()
                : collect(),
            'recentOrders' => $driver
                ? $driver->orders()
                    ->whereIn('status', [
                        OrderStatus::DELIVERED->value,
                        OrderStatus::CANCELLED->value,
                        OrderStatus::FAILED->value,
                    ])
                    ->latest('updated_at')
                    ->limit(5)
                    ->get()
                : collect(),
            'todaysDeliveryCount' => $driver ? $todaysDeliveredQuery()->count() : 0,
            'todaysEarnings' => $driver ? $todaysDeliveredQuery()->sum('driver_earning') : 0,
        ]);
    }
}
