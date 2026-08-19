<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreOrderFeedbackRequest;
use App\Models\Order;
use App\Services\Customers\SubmitDriverFeedbackService;
use Illuminate\Http\RedirectResponse;

/**
 * A customer rating the driver who delivered their order — reached from
 * the public tracking page (App\Http\Controllers\OrderTrackingController),
 * not a separate "my orders" area, same as order placement itself.
 * Unlike the rest of /track/*, this one route requires being logged in
 * as the order's own customer — see StoreOrderFeedbackRequest::authorize()
 * and the auth:customer middleware in routes/web.php.
 */
class OrderFeedbackController extends Controller
{
    public function store(StoreOrderFeedbackRequest $request, Order $order, SubmitDriverFeedbackService $service): RedirectResponse
    {
        $service->submit(
            $order,
            $request->user('customer'),
            $request->validated('rating'),
            $request->validated('comment'),
        );

        return redirect()->route('track.show', $order->tracking_token)
            ->with('status', __('Thanks for your feedback!'));
    }
}
