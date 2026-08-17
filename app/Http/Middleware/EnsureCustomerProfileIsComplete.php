<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The inverse of RedirectIfCustomerProfileComplete — this is the "must
 * complete your profile before going any further" gate that middleware's
 * own docblock said had no call site yet. Placing an order needs a phone
 * number (the driver has to be able to reach the customer) and a
 * delivery address, both collected on the complete-profile step, so
 * that's the one place this gate is actually needed so far.
 */
class EnsureCustomerProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');

        if ($customer?->phone === null) {
            return redirect()->route('customer.complete-profile.edit');
        }

        return $next($request);
    }
}
