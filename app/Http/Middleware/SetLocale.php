<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 9 — reads the session-stored locale (set by
 * App\Http\Controllers\LocaleController) and applies it for the current
 * request. Runs in the global `web` group (see bootstrap/app.php), so it
 * covers guests and authenticated users alike — every route in
 * routes/web.php already goes through `web`.
 *
 * Never trusts the session value blindly: only a locale actually present
 * in config('runix.locales.supported') is ever applied, so a stale/
 * tampered session value can't set an unsupported locale. No session
 * value (or an unsupported one) falls back to config('app.locale') — the
 * application's unchanged default.
 */
class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('runix.locales.supported');
        $sessionLocale = $request->session()->get('locale');

        $locale = in_array($sessionLocale, $supported, strict: true)
            ? $sessionLocale
            : config('app.locale');

        App::setLocale($locale);

        return $next($request);
    }
}
