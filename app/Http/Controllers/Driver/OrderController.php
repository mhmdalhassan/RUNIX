<?php

namespace App\Http\Controllers\Driver;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Services\Orders\OrderTransitionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Driver-scoped order access. Every lookup goes through
 * $request->user()->driver->orders() — a driver-scoped relation query, not
 * Order::findOrFail() plus a later authorization check — so a wrong id in
 * the URL 404s rather than ever reaching another driver's order (spec §9).
 *
 * transition() reuses OrderTransitionService directly — no second
 * transition system. The existing matrix already makes illegal hops (e.g.
 * jumping to `pending`) impossible regardless of who calls it; scoping to
 * the driver's own order is what stops them acting on someone else's.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $driver = $request->user()->driver;

        abort_if($driver === null, 404);

        return view('driver.orders.index', [
            'currentOrder' => $driver->currentOrder,
            'recentOrders' => $driver->orders()
                ->whereIn('status', [
                    OrderStatus::DELIVERED->value,
                    OrderStatus::CANCELLED->value,
                    OrderStatus::FAILED->value,
                ])
                ->latest('updated_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function show(Request $request, int $order): View
    {
        $driver = $request->user()->driver;

        abort_if($driver === null, 404);

        $orderModel = $driver->orders()->with('statusHistories.changedBy')->findOrFail($order);

        return view('driver.orders.show', [
            'order' => $orderModel,
            'allowedTransitions' => app(OrderTransitionService::class)->allowedTransitions($orderModel->status),
        ]);
    }

    public function transition(Request $request, int $order, OrderTransitionService $service): RedirectResponse
    {
        $driver = $request->user()->driver;

        abort_if($driver === null, 404);

        $orderModel = $driver->orders()->findOrFail($order);

        $validated = $request->validate([
            'to_status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->transition(
                $orderModel,
                OrderStatus::from($validated['to_status']),
                $request->user(),
                $validated['note'] ?? null,
            );
        } catch (InvalidOrderTransitionException $e) {
            return back()->withErrors(['to_status' => $e->getMessage()]);
        }

        return redirect()->route('driver.orders.show', $order)->with('status', 'Order status updated.');
    }
}
