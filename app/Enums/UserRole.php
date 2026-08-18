<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case DISPATCHER = 'dispatcher';
    case DRIVER = 'driver';
    // Scoped to exactly one restaurant (User::restaurant_id) — manages
    // that restaurant's own menu and profile only. See RestaurantPolicy/
    // MenuCategoryPolicy/MenuItemPolicy for the ownership check.
    case RESTAURANT_ADMIN = 'restaurant_admin';

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => __('Super Admin'),
            self::DISPATCHER => __('Dispatcher'),
            self::DRIVER => __('Driver'),
            self::RESTAURANT_ADMIN => __('Restaurant Admin'),
        };
    }
}
