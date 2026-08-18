<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;

/**
 * Note: SUPER_ADMIN bypasses all checks below via the Gate::before hook
 * registered in AppServiceProvider, so these methods only need to reason
 * about DISPATCHER and RESTAURANT_ADMIN (restaurant/menu management is
 * operational, not driver-only — same scope as CustomerPolicy/
 * DriverPolicy). Every dispatcher manages every restaurant, same as
 * every driver/customer; a RESTAURANT_ADMIN is the one exception — it's
 * scoped to exactly the one restaurant named by User::restaurant_id
 * (never null in practice for that role, but the null-safe comparison
 * below still denies cleanly if it somehow were).
 */
class RestaurantPolicy
{
    public function viewAny(User $user): bool
    {
        // Not RESTAURANT_ADMIN: listing every restaurant would leak the
        // existence of restaurants that aren't theirs. They're sent
        // straight to their own restaurant's show page instead (see
        // routes/dashboard.php's /dashboard redirect).
        return $user->isDispatcher();
    }

    public function view(User $user, Restaurant $restaurant): bool
    {
        return $user->isDispatcher() || $this->owns($user, $restaurant);
    }

    public function create(User $user): bool
    {
        // Not RESTAURANT_ADMIN: a restaurant admin manages the one
        // restaurant it's already linked to, it doesn't provision new ones.
        return $user->isDispatcher();
    }

    public function update(User $user, Restaurant $restaurant): bool
    {
        return $user->isDispatcher() || $this->owns($user, $restaurant);
    }

    public function delete(User $user, Restaurant $restaurant): bool
    {
        // Not RESTAURANT_ADMIN, even for their own restaurant — deleting
        // it is a bigger operational decision (cascades to every order
        // history reference) than this role is meant to make.
        return $user->isDispatcher();
    }

    private function owns(User $user, Restaurant $restaurant): bool
    {
        return $user->isRestaurantAdmin() && $user->restaurant_id === $restaurant->id;
    }
}
