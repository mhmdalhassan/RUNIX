<?php

namespace App\Services\Orders;

use App\Enums\OrderOfferResult;
use App\Events\DispatchActivityUpdated;
use App\Events\OrderStatusUpdated;
use App\Events\OrderTaken;
use App\Exceptions\DriverUnavailableException;
use App\Exceptions\OrderAlreadyClaimedException;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The shared-board counterpart to RespondToOrderOfferService::accept() —
 * a driver claiming straight off App\Http\Controllers\Driver\
 * AvailableOrdersController's board rather than responding to their own
 * OrderOffer. Both ultimately go through the same atomic
 * ClaimOrderForDriverService, so a board claim and an offer accept can
 * race each other for the same order exactly as safely as two offer
 * accepts already do (see ConcurrentOrderAcceptanceTest) — whichever
 * `UPDATE ... WHERE status = 'available'` commits first wins, the other
 * gets OrderAlreadyClaimedException.
 *
 * Unlike the offer-accept path, there's no OrderOffer required to start
 * from — that's the whole point of the board (any currently-eligible
 * driver can claim any AVAILABLE order, not just one they were
 * individually offered). Any OrderOffer rows that do exist for this
 * order are still settled here so the old per-driver offer inbox
 * (App\Http\Controllers\Driver\OrderOfferController, still reachable
 * directly) never shows a stale PENDING row for an order that's already
 * gone.
 */
class ClaimAvailableOrderService
{
    public function __construct(
        private readonly ClaimOrderForDriverService $claimService,
    ) {}

    /**
     * @throws DriverUnavailableException if $driver isn't currently
     *                                    eligible to claim anything (inactive, offline, or already
     *                                    occupied) — checked here up front, not just by
     *                                    ClaimOrderForDriverService's occupancy guard, since the board
     *                                    has no prior OrderOffer to have already gated this.
     * @throws OrderAlreadyClaimedException if another driver (offer-based
     *                                      or board-based) won the race.
     */
    public function claim(Order $order, Driver $driver, User $actor): Order
    {
        if (! $driver->is_active || ! $driver->is_online || $driver->current_order_id !== null) {
            throw new DriverUnavailableException($driver);
        }

        return DB::transaction(function () use ($order, $driver, $actor) {
            $claimed = $this->claimService->claim($order, $driver, $actor);

            // The claiming driver's own offer (if EligibleDriverFinder
            // happened to create one) becomes ACCEPTED rather than
            // CANCELLED by the bulk update below — same distinction
            // RespondToOrderOfferService's accept() makes.
            OrderOffer::query()
                ->where('order_id', $claimed->id)
                ->where('driver_id', $driver->id)
                ->where('result', OrderOfferResult::PENDING->value)
                ->update(['result' => OrderOfferResult::ACCEPTED->value, 'responded_at' => now()]);

            // Every other still-pending offer for this order is now moot
            // — mirrors RespondToOrderOfferService::accept() (spec §6
            // step 7), just triggered by a board claim instead of an
            // offer response.
            OrderOffer::query()
                ->where('order_id', $claimed->id)
                ->where('result', OrderOfferResult::PENDING->value)
                ->update(['result' => OrderOfferResult::CANCELLED->value, 'responded_at' => now()]);

            OrderTaken::dispatch($claimed->id, $driver->user->name);
            OrderStatusUpdated::dispatch($claimed);
            DispatchActivityUpdated::dispatch(
                sprintf('%s accepted by %s', $claimed->order_number, $driver->user->name),
                $claimed->id,
            );

            return $claimed;
        });
    }
}
