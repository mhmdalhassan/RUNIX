<?php

namespace App\Services\Orders;

use App\Enums\OrderOfferResult;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

/**
 * V1 eligibility only (spec §5/§2): no proximity/GPS filtering — every
 * active, online, unoccupied driver is eligible. Latitude/longitude are
 * never queried here; that's Phase 5.
 */
class EligibleDriverFinder
{
    /**
     * Drivers eligible for a fresh offer round on $order: active, online,
     * not currently occupied, and not already holding a PENDING offer for
     * this exact order (prevents duplicate concurrent offers to the same
     * driver on a re-offer round — a driver whose earlier offer expired or
     * was rejected is still eligible again).
     *
     * @return Collection<int, Driver>
     */
    public function forOrder(Order $order): Collection
    {
        return Driver::query()
            ->where('is_active', true)
            ->where('is_online', true)
            ->whereNull('current_order_id')
            ->whereDoesntHave('offers', function ($query) use ($order) {
                $query->where('order_id', $order->id)
                    ->where('result', OrderOfferResult::PENDING->value);
            })
            ->get();
    }
}
