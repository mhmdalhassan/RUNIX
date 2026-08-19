<?php

namespace App\Services\Orders;

use App\Enums\OrderOfferResult;
use App\Events\DispatchActivityUpdated;
use App\Events\OrderStatusUpdated;
use App\Events\OrderTaken;
use App\Exceptions\DriverUnavailableException;
use App\Exceptions\OrderAlreadyClaimedException;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The driver-facing half of the offer pipeline (spec §6 steps 5-7):
 * accepting or rejecting one specific offer. accept() delegates the actual
 * order+driver mutation to ClaimOrderForDriverService — this class only
 * owns the offer bookkeeping and broadcasting around it.
 */
class RespondToOrderOfferService
{
    public function __construct(
        private readonly ClaimOrderForDriverService $claimService,
    ) {}

    /**
     * @throws InvalidArgumentException if the offer isn't PENDING.
     * @throws OrderAlreadyClaimedException if another driver won the race.
     * @throws DriverUnavailableException if this driver is no longer
     *                                    claimable themselves by the time this offer is accepted —
     *                                    e.g. they already accepted a different pending offer of
     *                                    theirs first, or went offline/inactive in the meantime.
     *                                    Either exception marks this offer CANCELLED (the same result
     *                                    the other still-pending offers get on a win) before
     *                                    re-throwing, so the controller can turn it into a clean
     *                                    "no longer available" response instead of a 500.
     */
    public function accept(OrderOffer $offer, User $actor): OrderOffer
    {
        if ($offer->result !== OrderOfferResult::PENDING) {
            throw new InvalidArgumentException('This offer is no longer pending.');
        }

        try {
            return DB::transaction(function () use ($offer, $actor) {
                $order = $this->claimService->claim($offer->order, $offer->driver, $actor);

                $offer->update([
                    'result' => OrderOfferResult::ACCEPTED,
                    'responded_at' => now(),
                ]);

                // Every other still-pending offer for this order is now
                // moot (spec §6 step 7).
                OrderOffer::query()
                    ->where('order_id', $order->id)
                    ->where('id', '!=', $offer->id)
                    ->where('result', OrderOfferResult::PENDING->value)
                    ->update(['result' => OrderOfferResult::CANCELLED->value, 'responded_at' => now()]);

                OrderTaken::dispatch($order->id, $offer->driver->user->name);
                OrderStatusUpdated::dispatch($order);
                DispatchActivityUpdated::dispatch(
                    sprintf('%s accepted by %s', $order->order_number, $offer->driver->user->name),
                    $order->id,
                );

                return $offer->fresh();
            });
        } catch (OrderAlreadyClaimedException|DriverUnavailableException $e) {
            $offer->update([
                'result' => OrderOfferResult::CANCELLED,
                'responded_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * @throws InvalidArgumentException if the offer isn't PENDING.
     */
    public function reject(OrderOffer $offer, User $actor): OrderOffer
    {
        if ($offer->result !== OrderOfferResult::PENDING) {
            throw new InvalidArgumentException('This offer is no longer pending.');
        }

        $offer->update([
            'result' => OrderOfferResult::REJECTED,
            'responded_at' => now(),
        ]);

        return $offer->fresh();
    }
}
