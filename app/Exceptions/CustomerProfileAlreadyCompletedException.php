<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by App\Services\Customers\CompleteCustomerProfileService when the
 * authenticated customer's own row has already been consumed by a merge
 * (or otherwise deleted) between the request starting and reaching the
 * service — e.g. a double-submitted complete-profile form. Not expected to
 * reach a user in practice: the service only throws this when it can't
 * even fall back to whoever the current session now resolves to, which
 * means the session itself is in a genuinely inconsistent state.
 */
class CustomerProfileAlreadyCompletedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This customer profile has already been completed or no longer exists.');
    }
}
