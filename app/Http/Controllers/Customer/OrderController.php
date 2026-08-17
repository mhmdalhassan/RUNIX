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
        return redirect()
            ->route('track.show', $order->tracking_token)
            ->with('status', __('Your order has been placed!'));
    }
}
