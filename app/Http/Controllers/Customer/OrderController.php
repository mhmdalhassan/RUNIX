<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerOrderRequest;
use App\Models\Restaurant;
use App\Services\Orders\CreateCustomerOrderService;
use Illuminate\Http\RedirectResponse;

/**
 * A customer placing their own order from a restaurant's public menu —
 * the self-service counterpart to Admin\OrderController, which is
 * dispatcher-only. See App\Services\Orders\CreateCustomerOrderService
 * for the actual creation logic; this controller is just the HTTP edge.
 */
class OrderController extends Controller
{
    public function store(StoreCustomerOrderRequest $request, CreateCustomerOrderService $service): RedirectResponse
    {
        $restaurant = Restaurant::findOrFail($request->validated('restaurant_id'));

        $order = $service->create(
            $request->user('customer'),
            $restaurant,
            $request->validated('items'),
            $request->validated('delivery_address'),
            $request->validated('customer_notes'),
        );

        // The existing public tracking page (App\Http\Controllers\
        // OrderTrackingController) — no separate "my orders" page needed
        // this pass, the customer can watch their own order live the
        // same way anyone holding the tracking link can.
        //
        // order_just_placed_restaurant_id is a dedicated, one-shot flash
        // value (separate from the human-readable `status` message above)
        // — the tracking page uses its presence, not the message text, to
        // know it's safe to clear the client-side cart. It can only ever
        // be set on the one redirect a successful
        // CreateCustomerOrderService::create() call produces: never on a
        // validation failure (those redirect back to /cart with errors
        // instead) and never before the order actually exists.
        //
        // Carrying the restaurant id (not just a bare boolean) closes a
        // multi-tab race: localStorage is shared across every tab on this
        // origin, so if this tab's redirect is slow to load and the
        // customer meanwhile opens another tab and starts a NEW cart for
        // a different restaurant before this one finishes, the delayed
        // clear() must not wipe that unrelated in-progress cart out from
        // under them. The tracking page only clears when the cart's own
        // restaurantId still matches this order's — see track/show.blade.php.
        return redirect()
            ->route('track.show', $order->tracking_token)
            ->with('status', __('Your order has been placed!'))
            ->with('order_just_placed_restaurant_id', $order->restaurant_id);
    }
}
