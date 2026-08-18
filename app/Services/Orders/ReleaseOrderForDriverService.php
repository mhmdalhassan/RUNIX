<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Events\DispatchActivityUpdated;
use App\Events\OrderStatusUpdated;
use App\Exceptions\OrderNotReleasableException;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A driver backing out of an order they've claimed, before pickup — the
 * inverse of ClaimOrderForDriverService, and built the same way for the
 * same reason: both ends of "who currently holds this order" (orders.
 * driver_id / drivers.current_order_id) have to move together atomically,
 * so this bypasses OrderTransitionService (see its own docblock) exactly
 * like ClaimOrderForDriverService already does, using the same
 * conditional-`UPDATE`-as-lock technique instead of a read-then-write.
 *
 * Deliberately ACCEPTED-only — once PICKED_UP the driver physically has
 * the order, so "return it to the board for anyone to claim" no longer
 * makes sense; a driver cancelling after pickup still goes through the
 * ordinary CANCELLED/FAILED transitions (App\Http\Controllers\Driver\
 * OrderController::transition()), unchanged by this class.
 */
class ReleaseOrderForDriverService
{
    public function __construct(
        private readonly OfferOrderService $offerService,
    ) {}

    /**
     * @throws OrderNotReleasableException if the order wasn't ACCEPTED
     *                                     and assigned to $driver at the moment this ran (already picked
     *                                     up, already cancelled/reassigned, or never theirs).
     */
    public function release(Order $order, Driver $driver, ?User $actor, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $driver, $actor, $note) {
            $released = Order::query()
                ->whereKey($order->id)
                ->where('status', OrderStatus::ACCEPTED->value)
                ->where('driver_id', $driver->id)
                ->update([
                    'status' => OrderStatus::AVAILABLE->value,
                    'driver_id' => null,
                    'assigned_at' => null,
                    'accepted_at' => null,
                ]);

            if ($released === 0) {
                throw new OrderNotReleasableException($order);
            }

            // Guarded the same way ClaimOrderForDriverService guards the
            // reverse assignment — only clears occupancy if it's still
            // pointing at *this* order, in case something else already
            // moved the driver on concurrently.
            Driver::query()
                ->whereKey($driver->id)
                ->where('current_order_id', $order->id)
                ->update(['current_order_id' => null]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => OrderStatus::ACCEPTED,
                'to_status' => OrderStatus::AVAILABLE,
                'changed_by' => $actor?->id,
                'note' => $note,
            ]);

            $order->refresh();

            OrderStatusUpdated::dispatch($order);
            DispatchActivityUpdated::dispatch(
                sprintf('%s returned to Available by %s', $order->order_number, $driver->user->name),
                $order->id,
            );

            // The same "AVAILABLE -> eligible drivers get offered it"
            // step OrderTransitionService::transition() triggers for
            // every other route to AVAILABLE — this is the one other
            // place that needs it, so it's called directly rather than
            // routing through transition() just for this side effect.
            $this->offerService->offer($order);

            return $order;
        });
    }
}
