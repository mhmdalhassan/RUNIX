<?php

namespace App\Exceptions;

use App\Models\Driver;
use RuntimeException;

/**
 * Thrown by App\Services\Orders\ClaimOrderForDriverService when the
 * conditional `UPDATE drivers ... WHERE current_order_id IS NULL` affects
 * 0 rows — the driver became occupied by a different order between this
 * request starting and reaching the claim step (e.g. two offers accepted
 * back to back, or a concurrent manual assignment).
 */
class DriverUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly Driver $driver,
    ) {
        parent::__construct('This driver is no longer available — they already have an active order.');
    }
}
