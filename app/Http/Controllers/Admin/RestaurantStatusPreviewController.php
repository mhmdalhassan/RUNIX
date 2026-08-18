<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A single-purpose endpoint: remembers which weekday (0-6, Sunday-first)
 * a Dispatcher/Super Admin wants the restaurant show page's Status/Hours
 * card to preview — see User::statusPreviewWeekday(). Not restaurant-
 * scoped (no {restaurant} in the route) — it's one account-wide
 * preference reused across every restaurant page they visit, saved on
 * the User row itself rather than per-browser so it follows them across
 * devices. RESTAURANT_ADMIN never reaches this route (see routes/
 * dashboard.php's role middleware) — the picker isn't shown on their own
 * restaurant's page, they already know its schedule.
 */
class RestaurantStatusPreviewController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
        ]);

        $request->user()->update(['status_preview_weekday' => $validated['weekday']]);

        return back();
    }
}
