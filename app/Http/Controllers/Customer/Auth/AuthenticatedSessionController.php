<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login is no longer here — it's shared with staff at the one /login
 * endpoint (see Auth\AuthenticatedSessionController and its LoginRequest,
 * which tries the `web` guard then the `customer` guard). Logout stays
 * customer-specific: it only ever needs to end the customer guard's own
 * session, and the shared /logout is staff's.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
