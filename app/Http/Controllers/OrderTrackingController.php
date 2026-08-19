<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;

/**
 * Phase 7 — public, unauthenticated order tracking (routes/web.php's
 * `/track/{order:tracking_token}`). Deliberately no Gate::authorize() call:
 * OrderPolicy is for staff access, and this endpoint is intentionally
 * reachable by anyone holding the token, no login required.
 *
 * $order arrives already resolved by tracking_token — see the route
 * definition — so this never touches `id` at all. statusHistories,
 * driver.user (name only — see below), and feedback are eager-loaded;
 * customer/offers/driver's own phone or location are deliberately never
 * loaded here, so there's nothing sensitive in memory for the view to
 * accidentally render even by mistake.
 *
 * driver.user is loaded specifically so the view can show the assigned
 * driver's NAME once `driver_id` is set (any status — even after
 * delivery, since the feedback form still needs to say who it's rating).
 * That's the one piece of driver identity this public page shows; the
 * driver's phone number (Driver::$phone) and live coordinates
 * (Driver::$last_latitude/$last_longitude, User::$email) are never
 * selected here at all — see track/show.blade.php for where the name is
 * actually rendered, and OrderLocationTest for the coordinate-only
 * contract of the separate live-location endpoint this page polls.
 */
class OrderTrackingController extends Controller
{
    public function __invoke(Order $order): View
    {
        return view('track.show', [
            'order' => $order->load([
                'statusHistories',
                // Column-restricted on both levels — not just the view's
                // discipline but the query's own: Driver's phone/location
                // columns and User's email never even reach memory here.
                'driver:id,user_id',
                'driver.user:id,name',
                'feedback',
            ]),
        ]);
    }
}
