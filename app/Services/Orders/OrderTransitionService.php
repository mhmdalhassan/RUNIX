<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
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
 */
class OrderTransitionService
{
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
     * transaction.
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
            // happens through AssignDriverService, which sets driver_id
            // first and then calls back into this method. Blocking it
            // here (not just in the controller/UI) means no caller,
            // present or future, can create that invalid state.
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

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor?->id,
                'note' => $note,
            ]);

            return $order->refresh();
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
