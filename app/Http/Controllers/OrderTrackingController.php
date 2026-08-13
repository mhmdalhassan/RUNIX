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
 * definition — so this never touches `id` at all. Only statusHistories is
 * eager-loaded; driver/customer/offers are deliberately never loaded here,
 * so there's nothing sensitive in memory for the view to accidentally
 * render even by mistake.
 */
class OrderTrackingController extends Controller
{
    public function __invoke(Order $order): View
    {
        return view('track.show', [
            'order' => $order->load('statusHistories'),
        ]);
    }
}
