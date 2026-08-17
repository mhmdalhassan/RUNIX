<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The one shared /forgot-password endpoint for staff and customers,
 * mirroring how /login is shared (see AuthenticatedSessionController) —
 * tries the staff ('users') broker first, then falls back to the
 * ('customers') broker only when the email genuinely isn't a staff
 * account (not on every failure — a throttled staff request must still
 * report the throttle, not silently try the other broker and report a
 * misleading "no account" instead). The actual reset link a customer
 * receives points at their own /customer/reset-password/{token} — see
 * AppServiceProvider's ResetPassword::createUrlUsing().
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::broker('users')->sendResetLink($request->only('email'));

        if ($status === Password::INVALID_USER) {
            $status = Password::broker('customers')->sendResetLink($request->only('email'));
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
