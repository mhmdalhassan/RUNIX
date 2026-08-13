<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

/**
 * Expense management (`/admin/expenses`) is Super Admin only — real money
 * leaving the business, same sensitivity level as Staff Management
 * (UserPolicy) and driver_earning_override approval.
 *
 * SUPER_ADMIN already bypasses every check below via the Gate::before
 * hook in AppServiceProvider, so by the time any of these methods runs,
 * the caller is guaranteed to be a Dispatcher or Driver — always denied.
 * Route middleware `role:super_admin` enforces the same rule earlier in
 * the request; this is deliberate defense-in-depth, not the only gate.
 */
class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Expense $expense): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }
}
