<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the complete-profile routes only: a customer who's already
 * completed it (phone set) navigating back via browser history, a stale
 * bookmark, or a resubmitted form must not re-trigger
 * App\Services\Customers\CompleteCustomerProfileService's merge logic.
 * The inverse gate (forcing an incomplete profile to complete before
 * reaching anywhere else) has no call site yet — no other customer pages
 * exist this pass — so it isn't built until the storefront phase adds
 * something for it to guard.
 */
class RedirectIfCustomerProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');

        if ($customer?->phone !== null) {
            return redirect()->route('restaurants.index')->with('status', __('Your profile is already complete.'));
        }

        return $next($request);
    }
}
