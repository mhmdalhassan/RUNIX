<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Admin has full access to every policy/gate check.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Password reset emails go through the shared /forgot-password
        // endpoint now (see Auth\PasswordResetLinkController), which can
        // send via either the staff ('users') or customer ('customers')
        // broker — but the stock ResetPassword notification always
        // builds its link from the route literally named 'password.reset'
        // regardless of which broker sent it. That's the staff route;
        // a Customer needs their own 'customer.password.reset' instead,
        // so the reset step lands on their guard-specific page, not the
        // staff one (which would look up the token in the wrong table
        // entirely and fail).
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $routeName = $notifiable instanceof Customer ? 'customer.password.reset' : 'password.reset';

            return url(route($routeName, [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], absolute: false));
        });
    }
}
