<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RedirectIfCustomerProfileComplete;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'customer.profile.redirect-if-complete' => RedirectIfCustomerProfileComplete::class,
        ]);

        // Phase 9 — appended (not prepended) so it runs after
        // StartSession, which the `web` group already starts earlier;
        // reading session('locale') would fail before that.
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Login is shared between staff and customers at one /login
        // route (see Auth\AuthenticatedSessionController) — every
        // unauthenticated `auth`/`auth:customer` failure, staff or
        // customer route alike, lands on that same page now.
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Where an ALREADY-authenticated visitor lands when they hit a
        // guest-only route (the shared /login itself, or
        // /customer/register) has to be guard-aware, not path-based:
        // /login is reachable by both account types, so "which path was
        // this" can't tell us which one is actually logged in — only
        // checking the guards directly can. (`redirectGuestsTo` above
        // doesn't have this problem — it only ever has ONE destination
        // regardless of which guard failed.)
        $middleware->redirectUsersTo(fn () => Auth::guard('customer')->check()
            ? route('restaurants.index')
            : route('dashboard'));
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Backstop for offer expiry (spec §7) — see
        // App\Console\Commands\ExpireStaleOrderOffers's docblock for why
        // this exists alongside the delayed ExpireOrderOfferJob. Requires
        // the standard single cron entry running `php artisan schedule:run`
        // every minute (see the crontab line in the Phase 4 report) — it
        // does not need its own separate process.
        $schedule->command('orders:expire-stale-offers')->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
