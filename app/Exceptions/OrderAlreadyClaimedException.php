<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

/**
 * Thrown by App\Services\Orders\ClaimOrderForDriverService when the
 * conditional `UPDATE ... WHERE status = 'available'` affects 0 rows —
 * i.e. another driver (or a dispatcher) already claimed the order between
 * this request reading it and attempting to claim it. This is the
 * expected, clean outcome for the losing side of a race, not a bug.
 */
class OrderAlreadyClaimedException extends RuntimeException
{
    public function __construct(
        public readonly Order $order,
    ) {
        parent::__construct(sprintf(
            'Order [%s] is no longer available — it was already claimed.',
            $order->order_number,
        ));
    }
}
