<?php

namespace App\Http\Controllers\Driver;

use App\Enums\OrderOfferResult;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
            'locationStatus' => $this->locationStatus($driver),
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
            'deliveryHistory' => $driver
                ? $driver->deliveryHistoryQuery()->paginate(15)
                : new LengthAwarePaginator([], 0, 15),
        ]);
    }

    /**
     * Phase 5 §5 — a passive, informational-only read of the same
     * staleness window EligibleDriverFinder uses for ranking. Purely
     * descriptive: never affects offer eligibility, and null whenever
     * there's nothing meaningful to show (no driver record, or offline —
     * driver-location.js never submits a location while offline).
     */
    private function locationStatus(?Driver $driver): ?string
    {
        if ($driver === null || ! $driver->is_online) {
            return null;
        }

        if ($driver->last_location_at === null) {
            return 'not_shared';
        }

        $staleAfterMinutes = (int) config('runix.matching.location_stale_after_minutes');

        return $driver->last_location_at->lt(now()->subMinutes($staleAfterMinutes))
            ? 'stale'
            : 'shared';
    }
}
