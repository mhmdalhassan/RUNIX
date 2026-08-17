<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;

/**
 * Note: SUPER_ADMIN bypasses all checks below via the Gate::before hook
 * registered in AppServiceProvider, so these methods only need to reason
 * about DISPATCHER (restaurant/menu management is operational, not
 * driver-only — same scope as CustomerPolicy/DriverPolicy). No
 * per-dispatcher ownership exists anywhere in this app — every dispatcher
 * manages every restaurant, same as every driver/customer.
 */
class RestaurantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function view(User $user, Restaurant $restaurant): bool
    {
        return $user->isDispatcher();
    }

    public function create(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function update(User $user, Restaurant $restaurant): bool
    {
        return $user->isDispatcher();
    }

    public function delete(User $user, Restaurant $restaurant): bool
    {
        return $user->isDispatcher();
    }
}
