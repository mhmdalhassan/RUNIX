<?php

namespace App\Services\Customers;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\DriverFeedback;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one place a DriverFeedback row is ever created. StoreOrderFeedbackRequest
 * already checks ownership, DELIVERED status, and "not already reviewed"
 * before this is ever called — the same three checks are repeated here
 * as the actual write's own guard, same "never trust the caller fully"
 * shape as CreateCustomerOrderService re-checking restaurant/item state
 * inside its own transaction.
 */
class SubmitDriverFeedbackService
{
    /**
     * @throws InvalidArgumentException if $order isn't this customer's own,
     *                                  isn't DELIVERED, has no driver, or already has feedback —
     *                                  none of which StoreOrderFeedbackRequest should ever let
     *                                  through, so reaching this is itself a sign something
     *                                  upstream regressed.
     */
    public function submit(Order $order, Customer $customer, int $rating, ?string $comment): DriverFeedback
    {
        return DB::transaction(function () use ($order, $customer, $rating, $comment) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->customer_id !== $customer->id) {
                throw new InvalidArgumentException('This order does not belong to this customer.');
            }

            if ($order->status !== OrderStatus::DELIVERED) {
                throw new InvalidArgumentException('Feedback can only be left once an order has been delivered.');
            }

            if ($order->driver_id === null) {
                throw new InvalidArgumentException('This order has no driver to leave feedback for.');
            }

            if (DriverFeedback::where('order_id', $order->id)->exists()) {
                throw new InvalidArgumentException('Feedback has already been submitted for this order.');
            }

            return DriverFeedback::create([
                'order_id' => $order->id,
                'driver_id' => $order->driver_id,
                'customer_id' => $customer->id,
                'rating' => $rating,
                'comment' => $comment,
            ]);
        });
    }
}
