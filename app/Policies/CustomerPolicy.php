<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * Note: SUPER_ADMIN bypasses all checks below via the Gate::before hook
 * registered in AppServiceProvider, so these methods only need to reason
 * about DISPATCHER (customer management is operational, not driver-only).
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->isDispatcher();
    }

    public function create(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->isDispatcher();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isDispatcher();
    }
}
