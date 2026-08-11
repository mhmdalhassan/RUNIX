<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manual driver assignment only (Phase 3 — no automatic dispatch, no
 * driver offers, no first-driver-wins; those are Phase 4). Every status
 * hop this performs goes through OrderTransitionService, so assignment
 * can never land the order in a status the transition map doesn't allow.
 *
 * A dispatcher manually assigning a driver is treated as accepting on
 * that driver's behalf — there's no separate driver-side acceptance step
 * yet — so a PENDING order chains PENDING -> AVAILABLE -> ACCEPTED (two
 * logged transitions) and an AVAILABLE order goes AVAILABLE -> ACCEPTED.
 */
class AssignDriverService
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
    ) {}

    /**
     * @throws InvalidArgumentException if the driver isn't eligible or the
     *                                  order isn't in an assignable state.
     */
    public function assign(Order $order, Driver $driver, ?User $actor, ?string $note = null): Order
    {
        if ($order->driver_id !== null) {
            throw new InvalidArgumentException('This order already has a driver assigned.');
        }

        if (! in_array($order->status, [OrderStatus::PENDING, OrderStatus::AVAILABLE], strict: true)) {
            throw new InvalidArgumentException('This order is no longer available for assignment.');
        }

        if (! $driver->is_active || ! $driver->user->is_active) {
            throw new InvalidArgumentException('Only an active driver with an active account can be assigned.');
        }

        return DB::transaction(function () use ($order, $driver, $actor, $note) {
            $order->driver_id = $driver->id;
            $order->assigned_at = now();
            $order->save();
            $order->refresh();

            if ($order->status === OrderStatus::PENDING) {
                $order = $this->transitions->transition($order, OrderStatus::AVAILABLE, $actor, $note);
            }

            return $this->transitions->transition($order, OrderStatus::ACCEPTED, $actor, $note);
        });
    }
}
