<?php

namespace App\Policies;

use App\Models\MenuCategory;
use App\Models\Restaurant;
use App\Models\User;

/**
 * Note: SUPER_ADMIN bypasses all checks below via the Gate::before hook
 * registered in AppServiceProvider — see RestaurantPolicy's docblock for
 * the full reasoning (identical here, including the RESTAURANT_ADMIN
 * ownership check). create() takes the target Restaurant explicitly
 * (there's no MenuCategory instance yet to read a restaurant_id off of)
 * — callers authorize it as `Gate::authorize('create', [MenuCategory::class, $restaurant])`.
 */
class MenuCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDispatcher();
    }

    public function view(User $user, MenuCategory $menuCategory): bool
    {
        return $user->isDispatcher() || $this->owns($user, $menuCategory->restaurant_id);
    }

    public function create(User $user, Restaurant $restaurant): bool
    {
        return $user->isDispatcher() || $this->owns($user, $restaurant->id);
    }

    public function update(User $user, MenuCategory $menuCategory): bool
    {
        return $user->isDispatcher() || $this->owns($user, $menuCategory->restaurant_id);
    }

    public function delete(User $user, MenuCategory $menuCategory): bool
    {
        return $user->isDispatcher() || $this->owns($user, $menuCategory->restaurant_id);
    }

    private function owns(User $user, int $restaurantId): bool
    {
        return $user->isRestaurantAdmin() && $user->restaurant_id === $restaurantId;
    }
}
