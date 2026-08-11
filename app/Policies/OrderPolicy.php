<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Note: SUPER_ADMIN bypasses all checks below via the Gate::before hook
 * registered in AppServiceProvider, so these methods only need to reason
 * about DISPATCHER and DRIVER.
 *
 * Driver-specific order access (a driver viewing/acting on their own
 * orders) is Phase 4 — in Phase 3 a DRIVER gets no grants here at all,
 * and the admin Orders routes stay behind the existing
 * `role:dispatcher,super_admin` middleware group, same as
 * Drivers/Customers today. `assign`/`transition` reuse `update` — they're
 * operational actions on an existing order, not a distinct capability.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isDispatcher();
    }

    public function create(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isDispatcher();
    }
}
