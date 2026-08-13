<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 9 — public locale switch (routes/web.php's `/locale/{locale}`).
 * Deliberately no auth/role gate: switching display language isn't a
 * privileged action, and it must work for a guest on the login or public
 * tracking page just as well as a logged-in user (spec §4).
 *
 * Session-only persistence — no `users` column, matching the theme
 * toggle's own client-side-only precedent for a display preference.
 * App\Http\Middleware\SetLocale reads what's stored here on every
 * subsequent request.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, config('runix.locales.supported'), strict: true), 404);

        $request->session()->put('locale', $locale);

        // Deliberately not redirect()->back() — Illuminate\Routing\UrlGenerator::previous()
        // trusts the client-supplied Referer header first (returning an
        // absolute URL verbatim if one is given), which turns this public,
        // unauthenticated GET into an open redirect via a spoofed Referer.
        // session()->previousUrl() is Laravel's own server-tracked "last
        // GET this app served in this session" value — never derived from
        // a client-controlled header, so it can't point off-site.
        return redirect()->to($request->session()->previousUrl() ?? '/');
    }
}
