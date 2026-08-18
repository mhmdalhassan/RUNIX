<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

/**
 * Thrown by App\Services\Orders\ReleaseOrderForDriverService when the
 * conditional `UPDATE ... WHERE status = 'accepted' AND driver_id = ?`
 * affects 0 rows — the order has already moved on (picked up, cancelled
 * by a dispatcher, reassigned) since this driver's page loaded, or it was
 * never theirs to begin with. Same "expected outcome of a race, not a
 * bug" spirit as OrderAlreadyClaimedException.
 */
class OrderNotReleasableException extends RuntimeException
{
    public function __construct(
        public readonly Order $order,
    ) {
        parent::__construct(sprintf(
            'Order [%s] can no longer be returned to the available board — it may already have been picked up or reassigned.',
            $order->order_number,
        ));
    }
}
