<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Events\DispatchActivityUpdated;
use App\Events\OrderStatusUpdated;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single authoritative place order status changes happen. Every
 * status change in the application — a UI action button, driver
 * assignment, a future automation — must go through transition() or
 * recordInitial() here. Nothing else may write Order::status directly or
 * insert an OrderStatusHistory row; that keeps "every transition creates
 * exactly one history record" true by construction instead of by
 * discipline.
 *
 * The two sanctioned exceptions are ClaimOrderForDriverService and
 * ReleaseOrderForDriverService — each moves orders.driver_id/drivers.
 * current_order_id together with the status change, atomically, and a
 * generic transition() can't offer that same one-UPDATE race guarantee
 * (see either class's own docblock).
 */
class OrderTransitionService
{
    public function __construct(
        private readonly OfferOrderService $offerService,
    ) {}

    /**
     * Terminal statuses (DELIVERED/CANCELLED/FAILED) have no entry here,
     * which is what makes them terminal — canTransition() simply has
     * nothing to allow.
     *
     * @var array<string, list<OrderStatus>>
     */
    private const TRANSITIONS = [
        'pending' => [OrderStatus::AVAILABLE, OrderStatus::CANCELLED],
        'available' => [OrderStatus::ACCEPTED, OrderStatus::CANCELLED],
        'accepted' => [OrderStatus::PICKED_UP, OrderStatus::CANCELLED, OrderStatus::FAILED],
        'picked_up' => [OrderStatus::ON_THE_WAY, OrderStatus::CANCELLED, OrderStatus::FAILED],
        'on_the_way' => [OrderStatus::DELIVERED, OrderStatus::FAILED, OrderStatus::CANCELLED],
    ];

    /**
     * @return list<OrderStatus>
     */
    public function allowedTransitions(OrderStatus $from): array
    {
        return self::TRANSITIONS[$from->value] ?? [];
    }

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions($from), strict: true);
    }

    /**
     * Moves $order to $to: stamps the matching `*_at` column, saves, and
     * writes exactly one OrderStatusHistory row — all inside one
     * transaction. Also releases the assigned driver's occupancy
     * (`current_order_id`) whenever $to is terminal — the necessary
     * counterpart to ClaimOrderForDriverService occupying them, otherwise
     * a driver would be stranded "busy" forever after their first
     * delivery (see the class docblock on Driver::currentOrder()).
     *
     * @throws InvalidOrderTransitionException if $to isn't a legal hop
     *                                         from the order's current status (this also covers every
     *                                         attempt to transition out of a terminal status, since those
     *                                         have no allowed transitions at all).
     */
    public function transition(Order $order, OrderStatus $to, ?User $actor, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $note) {
            $from = $order->status;

            if (! $this->canTransition($from, $to)) {
                throw new InvalidOrderTransitionException($from, $to);
            }

            // An order can't be ACCEPTED without a driver — that hop only
            // happens through AssignDriverService/RespondToOrderOfferService
            // (both go through ClaimOrderForDriverService), which set
            // driver_id first and then call back into this method.
            // Blocking it here (not just in the controller/UI) means no
            // caller, present or future, can create that invalid state.
            if ($to === OrderStatus::ACCEPTED && $order->driver_id === null) {
                throw new InvalidOrderTransitionException($from, $to);
            }

            $order->status = $to;

            match ($to) {
                OrderStatus::ACCEPTED => $order->accepted_at = now(),
                OrderStatus::PICKED_UP => $order->picked_up_at = now(),
                OrderStatus::DELIVERED => $order->delivered_at = now(),
                OrderStatus::CANCELLED => $order->cancelled_at = now(),
                OrderStatus::FAILED => $order->failed_at = now(),
                default => null,
            };

            $order->save();

            if ($to->isTerminal() && $order->driver_id !== null) {
                Driver::query()
                    ->where('id', $order->driver_id)
                    ->where('current_order_id', $order->id)
                    ->update(['current_order_id' => null]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor?->id,
                'note' => $note,
            ]);

            $order->refresh();

            // The other half of "order becomes AVAILABLE -> eligible
            // drivers receive the offer" (spec's core objective) — this is
            // the one place every route to AVAILABLE funnels through
            // (the dispatcher's status-update button, and any future
            // automation), so it's the only place that needs to know
            // about offering at all. OfferOrderService itself is a no-op
            // if $order somehow isn't AVAILABLE by the time it runs, so
            // this is safe even if that invariant is ever loosened.
            if ($to === OrderStatus::AVAILABLE) {
                $this->offerService->offer($order);
            }

            OrderStatusUpdated::dispatch($order);
            DispatchActivityUpdated::dispatch(
                sprintf('%s -> %s', $order->order_number, $to->label()),
                $order->id,
            );

            return $order;
        });
    }

    /**
     * Writes the initial history row for a brand-new order. $order's
     * status is already PENDING (the column default) by the time this
     * runs; from_status is null, marking it as the order's origin point.
     */
    public function recordInitial(Order $order, ?User $actor): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => $order->status,
            'changed_by' => $actor?->id,
            'note' => null,
        ]);
    }
}
