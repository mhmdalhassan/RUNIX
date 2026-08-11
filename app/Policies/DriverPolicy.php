<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

/**
 * Note: SUPER_ADMIN bypasses all checks below via the Gate::before hook
 * registered in AppServiceProvider, so these methods only need to reason
 * about DISPATCHER and DRIVER.
 */
class DriverPolicy
{
    /**
     * Dispatchers can list all drivers (needed to manage operations).
     */
    public function viewAny(User $user): bool
    {
        return $user->isDispatcher();
    }

    /**
     * Dispatchers can view any driver; a driver can view their own profile.
     */
    public function view(User $user, Driver $driver): bool
    {
        return $user->isDispatcher() || $user->id === $driver->user_id;
    }

    /**
     * Only staff provision driver accounts, not drivers themselves.
     */
    public function create(User $user): bool
    {
        return $user->isDispatcher();
    }

    /**
     * Dispatchers can update any driver; a driver can update their own
     * profile (e.g. online status, current location).
     */
    public function update(User $user, Driver $driver): bool
    {
        return $user->isDispatcher() || $user->id === $driver->user_id;
    }

    /**
     * Driver deactivation is a staff-only action.
     */
    public function delete(User $user, Driver $driver): bool
    {
        return $user->isDispatcher();
    }
}
