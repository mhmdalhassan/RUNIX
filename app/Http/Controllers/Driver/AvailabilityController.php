<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Toggles a driver's own `is_online` flag — the actual precondition (with
 * `is_active` and an empty `current_order_id`) for receiving new offers,
 * see App\Services\Orders\EligibleDriverFinder. Scoped to the
 * authenticated driver's own profile only; there is no {driver} route
 * parameter to manipulate someone else's.
 */
class AvailabilityController extends Controller
{
    public function toggle(Request $request): RedirectResponse
    {
        $driver = $request->user()->driver;

        abort_if($driver === null, 404);

        $driver->update([
            'is_online' => ! $driver->is_online,
            'last_seen_at' => now(),
        ]);

        return back()->with('status', $driver->is_online ? 'You are now online.' : 'You are now offline.');
    }
}
