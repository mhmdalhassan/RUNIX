<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case DISPATCHER = 'dispatcher';
    case DRIVER = 'driver';

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::DISPATCHER => 'Dispatcher',
            self::DRIVER => 'Driver',
        };
    }
}
