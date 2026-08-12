<?php

namespace App\Services\Orders;

use App\Enums\OrderOfferResult;
use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manual driver assignment (spec §14). As of Phase 4 this shares the same
 * atomic claim primitive as the driver self-accept flow
 * (ClaimOrderForDriverService), so both paths always leave
 * `orders.driver_id`/`drivers.current_order_id` in the same consistent
 * state — there is no separate, inconsistent assignment path.
 *
 * A dispatcher manually assigning a driver is treated as accepting on
 * that driver's behalf — there's no separate driver-side acceptance step
 * for this path — so a PENDING order first transitions to AVAILABLE (via
 * OrderTransitionService, unchanged from Phase 3) and then goes through
 * the same AVAILABLE -> ACCEPTED claim an offer-accept would.
 */
class AssignDriverService
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
        private readonly ClaimOrderForDriverService $claimService,
    ) {}

    /**
     * @throws InvalidArgumentException if the order isn't assignable or
     *                                  the driver isn't eligible.
     */
    public function assign(Order $order, Driver $driver, ?User $actor, ?string $note = null): Order
    {
        if ($order->driver_id !== null) {
            throw new InvalidArgumentException('This order already has a driver assigned.');
        }

        if (! in_array($order->status, [OrderStatus::PENDING, OrderStatus::AVAILABLE], strict: true)) {
            throw new InvalidArgumentException('This order is no longer available for assignment.');
        }

        // Phase 4 tightens this vs. Phase 3: manual assignment must also
        // respect online status and occupancy (spec §14), not just active.
        if (! $driver->is_active || ! $driver->user->is_active) {
            throw new InvalidArgumentException('Only an active driver with an active account can be assigned.');
        }

        if (! $driver->is_online) {
            throw new InvalidArgumentException('Only an online driver can be assigned.');
        }

        if ($driver->current_order_id !== null) {
            throw new InvalidArgumentException('This driver already has an active order.');
        }

        return DB::transaction(function () use ($order, $driver, $actor, $note) {
            if ($order->status === OrderStatus::PENDING) {
                $order = $this->transitions->transition($order, OrderStatus::AVAILABLE, $actor, $note);
            }

            // A manual assignment bypasses the offer system entirely —
            // any offers already out for this order are now moot.
            OrderOffer::query()
                ->where('order_id', $order->id)
                ->where('result', OrderOfferResult::PENDING->value)
                ->update(['result' => OrderOfferResult::CANCELLED->value, 'responded_at' => now()]);

            return $this->claimService->claim($order, $driver, $actor, $note);
        });
    }
}
