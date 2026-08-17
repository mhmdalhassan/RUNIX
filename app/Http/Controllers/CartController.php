<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The cart itself lives entirely client-side (localStorage — see
 * resources/js/runix/cart.js), so this controller has nothing to fetch;
 * it just renders the page shell and whatever server-only context the
 * checkout form needs (the flat delivery fee, and — if the visitor
 * happens to already be signed in as a customer — their saved address to
 * prefill). Deliberately reachable by anyone, same as the restaurant
 * pages: browsing/reviewing a cart needs no login, only the final
 * "Place Order" submission does (see routes/customer.php's
 * customer.orders.store, gated by auth:customer +
 * customer.profile.require-complete).
 */
class CartController extends Controller
{
    public function show(Request $request): View
    {
        $customer = $request->user('customer');

        return view('cart.show', [
            'deliveryFee' => (float) config('runix.customer_ordering.default_delivery_fee'),
            'customerAddress' => $customer?->address,
            'profileComplete' => $customer !== null && $customer->phone !== null,
        ]);
    }
}
