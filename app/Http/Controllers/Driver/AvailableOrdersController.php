<?php

namespace App\Http\Controllers\Driver;

use App\Exceptions\DriverUnavailableException;
use App\Exceptions\OrderAlreadyClaimedException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\ClaimAvailableOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The shared "Available Orders" board (replaces the private per-driver
 * offer inbox as the driver's primary way to find work — see
 * App\Http\Controllers\Driver\OrderOfferController's docblock for what
 * still runs underneath it). Every driver with a driver profile sees the
 * exact same Order::available() list; there's no per-driver filtering of
 * *what's shown*, only of what they're currently allowed to *claim* (see
 * ClaimAvailableOrderService).
 */
class AvailableOrdersController extends Controller
{
    /**
     * `?partial=1` returns just the board fragment (no layout) — used by
     * resources/js/runix/driver-available-orders.js's poll, same pattern
     * OrderOfferController::index() already established.
     */
    public function index(Request $request): View
    {
        $driver = $request->user()->driver;

        abort_if($driver === null, 404);

        $orders = Order::available()->oldest('created_at')->get();

        if ($request->boolean('partial')) {
            return view('driver.partials.available-orders-list', ['orders' => $orders, 'driver' => $driver]);
        }

        return view('driver.available-orders.index', ['orders' => $orders, 'driver' => $driver]);
    }

    public function claim(Request $request, Order $order, ClaimAvailableOrderService $service): RedirectResponse
    {
        $driver = $request->user()->driver;

        abort_if($driver === null, 404);

        try {
            $service->claim($order, $driver, $request->user());
        } catch (OrderAlreadyClaimedException|DriverUnavailableException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard')->with('status', 'Order accepted.');
    }
}
