<?php

namespace App\Services\Orders;

use App\Enums\OrderOfferResult;
use App\Enums\OrderStatus;
use App\Events\OrderAvailable;
use App\Jobs\ExpireOrderOfferJob;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Notifications\NewOrderOfferNotification;
use Illuminate\Database\Eloquent\Collection;

/**
 * Creates one OrderOffer per eligible driver for an AVAILABLE order
 * (spec §6), notifies each driver, and schedules each offer's 2-minute
 * expiry. Also broadcasts OrderAvailable so every driver's shared board
 * (App\Http\Controllers\Driver\AvailableOrdersController) can refresh
 * immediately — the board itself lists straight from Order::available(),
 * not from these OrderOffer rows, so it shows the order to any eligible
 * driver regardless of whether they happened to be online/eligible at
 * the exact moment this loop ran. A no-op (returns an empty collection,
 * no broadcast) if the order isn't actually AVAILABLE — callers don't
 * need to check first.
 */
class OfferOrderService
{
    public function __construct(
        private readonly EligibleDriverFinder $finder,
    ) {}

    /**
     * @return Collection<int, OrderOffer>
     */
    public function offer(Order $order): Collection
    {
        if ($order->status !== OrderStatus::AVAILABLE) {
            return new Collection;
        }

        OrderAvailable::dispatch($order->id);

        $drivers = $this->finder->forOrder($order);
        $offers = new Collection;

        foreach ($drivers as $driver) {
            $offer = OrderOffer::create([
                'order_id' => $order->id,
                'driver_id' => $driver->id,
                'offered_at' => now(),
                'result' => OrderOfferResult::PENDING,
            ]);

            $offers->push($offer);

            $driver->user->notify(new NewOrderOfferNotification($offer));

            ExpireOrderOfferJob::dispatch($offer->id)->delay(now()->addMinutes(2));
        }

        return $offers;
    }
}
