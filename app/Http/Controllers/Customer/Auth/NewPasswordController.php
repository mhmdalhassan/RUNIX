<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

/**
 * Mirrors App\Http\Controllers\Auth\NewPasswordController, pointed at the
 * named `customers` broker and the Customer model. The *request* step
 * (emailing the link) is shared with staff at /forgot-password — see
 * Auth\PasswordResetLinkController — this is only the reset-with-token
 * step, which stays customer-specific since the token itself already
 * ties it to this broker.
 */
class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('customer.auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Customer $customer) use ($request) {
                $customer->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($customer));
            }
        );

        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
