<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The one shared /login endpoint for staff and customers — see
 * LoginRequest's own docblock for how authenticate() picks the guard.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $isCustomer = $request->authenticatedGuard() === 'customer';

        $this->discardIntendedUrlIfWrongAccountType($request, $isCustomer);

        if ($isCustomer) {
            $customer = Auth::guard('customer')->user();

            // A customer's "home" is the restaurant listing, not the
            // marketing welcome page — that page is for logged-out
            // visitors deciding whether to sign up at all.
            return $customer->phone === null
                ? redirect()->route('customer.complete-profile.edit')
                : redirect()->intended(route('restaurants.index', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * redirect()->intended() reads whatever URL bounced the visitor to
     * this now-shared login page — but that URL was captured before we
     * knew which account type they'd turn out to be. A customer who
     * first hit /admin/dashboard (unauthenticated) and then logs in with
     * customer credentials must NOT be sent back to /admin/dashboard
     * (they have no access there — it would just bounce them right back
     * to login); the same the other way for a staff member who first
     * hit a /customer/* page. Discarding a mismatched intended URL here
     * lets intended() safely fall through to the correct default below.
     */
    private function discardIntendedUrlIfWrongAccountType(Request $request, bool $isCustomer): void
    {
        $intended = $request->session()->get('url.intended');

        if ($intended === null) {
            return;
        }

        $intendedIsCustomerPath = str_starts_with(parse_url($intended, PHP_URL_PATH) ?? '', '/customer');

        if ($intendedIsCustomerPath !== $isCustomer) {
            $request->session()->forget('url.intended');
        }
    }
}
