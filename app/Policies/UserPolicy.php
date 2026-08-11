<?php

namespace App\Policies;

use App\Models\User;

/**
 * Staff Management (`/admin/users`) is Super Admin only.
 *
 * SUPER_ADMIN already bypasses every check below via the Gate::before
 * hook in AppServiceProvider, so by the time any of these methods runs,
 * the caller is guaranteed to be a Dispatcher or Driver — always denied.
 * (Route middleware `role:super_admin` enforces the same rule earlier in
 * the request, so this is deliberate defense-in-depth, not the only
 * gate.)
 *
 * This does not, by itself, stop a Super Admin from managing another
 * Super Admin account — Gate::before short-circuits before these methods
 * ever run. That restriction is enforced separately in
 * Admin\UserController / User::scopeStaff(), which excludes SUPER_ADMIN
 * rows from the staff-management queries entirely.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $target): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $target): bool
    {
        return false;
    }
}
