<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\DriverUnavailableException;
use App\Exceptions\OrderAlreadyClaimedException;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one atomic "claim this order for this driver" primitive (spec §3),
 * shared by the offer-accept flow (RespondToOrderOfferService) and manual
 * dispatcher assignment (AssignDriverService) — so both paths always leave
 * `orders.driver_id`/`drivers.current_order_id` in the same consistent
 * state, and neither can produce a race.
 *
 * Deliberately NOT "read order, check status, update order." Both
 * mutations are conditional `UPDATE`s guarded in their own `WHERE` clause;
 * the affected-row count is the authoritative signal of who won. InnoDB
 * takes a row lock on an `UPDATE`'s matched rows immediately (a "current
 * read," not the transaction's repeatable-read snapshot), so a second,
 * genuinely concurrent `UPDATE ... WHERE status = 'available'` against the
 * same row blocks until the first commits, then evaluates its `WHERE`
 * against the now-changed row and matches nothing — see
 * tests/Feature/ConcurrentOrderAcceptanceTest.php, which proves this
 * against real MySQL with two separate processes/connections, not a
 * sequential simulation.
 *
 * Assumes the order is already AVAILABLE — a caller starting from PENDING
 * (manual assignment can) must transition it to AVAILABLE first.
 *
 * The driver-side `UPDATE` also re-checks `is_active`/`is_online` in its
 * own `WHERE` clause, not just occupancy — this is the one place all three
 * callers (offer-accept, board-claim, manual assignment) converge, so it's
 * the only spot that can guarantee the check atomically for all of them.
 * ClaimAvailableOrderService/AssignDriverService additionally pre-check
 * this themselves for a friendlier error before opening a transaction, but
 * neither of those pre-checks can close the gap between "checked" and
 * "the UPDATE actually runs" the way a `WHERE` clause on the same
 * statement can — a driver going offline/inactive in that exact window is
 * still caught here.
 */
class ClaimOrderForDriverService
{
    /**
     * @throws OrderAlreadyClaimedException if the order wasn't AVAILABLE
     *                                      at claim time (someone else already won it).
     * @throws DriverUnavailableException if the driver isn't currently
     *                                    claimable — occupied by another order, or (as of this check)
     *                                    inactive/offline — whether that was already true or became
     *                                    true concurrently with this call.
     */
    public function claim(Order $order, Driver $driver, ?User $actor, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $driver, $actor, $note) {
            $orderClaimed = Order::query()
                ->whereKey($order->id)
                ->where('status', OrderStatus::AVAILABLE->value)
                ->update([
                    'status' => OrderStatus::ACCEPTED->value,
                    'driver_id' => $driver->id,
                    'assigned_at' => now(),
                    'accepted_at' => now(),
                ]);

            if ($orderClaimed === 0) {
                throw new OrderAlreadyClaimedException($order);
            }

            $driverClaimed = Driver::query()
                ->whereKey($driver->id)
                ->whereNull('current_order_id')
                ->where('is_active', true)
                ->where('is_online', true)
                ->update([
                    'current_order_id' => $order->id,
                ]);

            if ($driverClaimed === 0) {
                throw new DriverUnavailableException($driver);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => OrderStatus::AVAILABLE,
                'to_status' => OrderStatus::ACCEPTED,
                'changed_by' => $actor?->id,
                'note' => $note,
            ]);

            return $order->refresh();
        });
    }
}
