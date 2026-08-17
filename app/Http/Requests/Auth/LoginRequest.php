<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The one shared /login form for both staff (`web` guard, User) and
 * customers (`customer` guard, Customer) — authenticate() tries `web`
 * first, then `customer`, and records which one actually matched via
 * authenticatedGuard() so AuthenticatedSessionController::store() knows
 * where to send the response. Never reveals which table (or neither) an
 * email belongs to: a wrong password and a wrong guard fail identically.
 */
class LoginRequest extends FormRequest
{
    /**
     * Which guard authenticate() actually succeeded on ('web' or
     * 'customer') — null until authenticate() has run successfully.
     */
    private ?string $authenticatedGuard = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials against each
     * guard in turn.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // `is_active` is an extra query condition, not a credential: a
        // deactivated account fails the same way a wrong password does,
        // so login never reveals whether an email belongs to a
        // deactivated account.
        $credentials = [...$this->only('email', 'password'), 'is_active' => true];
        $remember = $this->boolean('remember');

        // Staff first: if the same email/password combination somehow
        // validates against both tables (nothing prevents that today —
        // they're independent unique constraints), the staff account
        // wins the ambiguity, since staff accounts are provisioned
        // deliberately rather than self-registered.
        if (Auth::guard('web')->attempt($credentials, $remember)) {
            $this->authenticatedGuard = 'web';
            RateLimiter::clear($this->throttleKey());

            return;
        }

        if (Auth::guard('customer')->attempt($credentials, $remember)) {
            $this->authenticatedGuard = 'customer';
            RateLimiter::clear($this->throttleKey());

            return;
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    /**
     * Which guard authenticate() succeeded on. Only meaningful after a
     * successful authenticate() call.
     */
    public function authenticatedGuard(): ?string
    {
        return $this->authenticatedGuard;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request. Not
     * guard-namespaced — one shared bucket per email+ip regardless of
     * which account type it turns out to belong to, since this is one
     * shared form now.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
