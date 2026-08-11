<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use RuntimeException;

/**
 * Thrown by OrderTransitionService when asked to move an order between two
 * statuses that aren't a legal hop — including any attempt to transition
 * out of a terminal status. Controllers catch this and turn it into a
 * 422/validation-style response; it should never surface as a raw 500.
 */
class InvalidOrderTransitionException extends RuntimeException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {
        parent::__construct(sprintf(
            'Cannot transition order from [%s] to [%s].',
            $from->value,
            $to->value,
        ));
    }
}
