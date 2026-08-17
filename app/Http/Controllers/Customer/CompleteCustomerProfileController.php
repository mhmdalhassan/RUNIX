<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CompleteCustomerProfileRequest;
use App\Models\Customer;
use App\Services\Customers\CompleteCustomerProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompleteCustomerProfileController extends Controller
{
    /**
     * Display the complete-your-profile form. The
     * customer.profile.redirect-if-complete route middleware already
     * keeps an already-completed customer from reaching here at all.
     */
    public function edit(Request $request): View
    {
        return view('customer.complete-profile', [
            'customer' => $request->user('customer'),
        ]);
    }

    /**
     * Handle the phone/address submission — see
     * App\Services\Customers\CompleteCustomerProfileService for the
     * match-or-update logic this delegates to.
     */
    public function update(CompleteCustomerProfileRequest $request, CompleteCustomerProfileService $service): RedirectResponse
    {
        /** @var Customer $registered */
        $registered = $request->user('customer');

        $survivor = $service->complete(
            $registered,
            $request->validated('phone'),
            $request->validated('address'),
        );

        if ($survivor->isNot($registered)) {
            // A merge happened — $registered's row was deleted
            // mid-request. Re-authenticate onto the surviving row so
            // both the session payload and the guard's in-memory user
            // are updated; without this, this response looks fine (PHP
            // still holds the old in-memory object) but the customer is
            // silently logged out on their very next request, since
            // SessionGuard::user() would then try to look up the
            // deleted id and find nothing.
            Auth::guard('customer')->login($survivor);
            $request->session()->regenerate();
        }

        // Same destination as a normal login (see
        // Auth\AuthenticatedSessionController::store()) — a customer's
        // "home" is the restaurant listing, not the marketing page.
        return redirect()->route('restaurants.index')->with('status', __('Your profile is complete.'));
    }
}
