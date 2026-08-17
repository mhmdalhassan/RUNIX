<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

/**
 * Note: SUPER_ADMIN bypasses all checks below via the Gate::before hook
 * registered in AppServiceProvider — see RestaurantPolicy's docblock for
 * the full reasoning (identical here).
 */
class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function view(User $user, MenuItem $menuItem): bool
    {
        return $user->isDispatcher();
    }

    public function create(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function update(User $user, MenuItem $menuItem): bool
    {
        return $user->isDispatcher();
    }

    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $user->isDispatcher();
    }
}
