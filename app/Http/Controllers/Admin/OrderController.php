<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Services\Orders\AssignDriverService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\OrderTransitionService;
use App\Services\Orders\UpdateOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class OrderController extends Controller
{
    /**
     * List orders with optional status/driver/date/search filtering.
     * Indexed columns only (status, driver_id, created_at, order_number);
     * never loads the full table into PHP to filter.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Order::class);

        $status = $request->string('status')->value();
        $driverId = $request->integer('driver_id') ?: null;
        $date = $request->string('date')->value();
        $search = $request->string('search')->trim()->value();

        $orders = Order::query()
            ->with(['customer', 'driver.user'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_phone_snapshot', 'like', "%{$search}%");
                });
            })
            ->when($status !== '' && OrderStatus::tryFrom($status), fn ($query) => $query->where('status', $status))
            ->when($driverId, fn ($query, int $id) => $query->where('driver_id', $id))
            ->when($date !== '', fn ($query) => $query->whereDate('created_at', $date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'search' => $search,
            'status' => $status,
            'driverId' => $driverId,
            'date' => $date,
            'drivers' => Driver::query()->with('user')->where('is_active', true)->get(),
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Order::class);

        return view('admin.orders.create', [
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(),
            'drivers' => Driver::query()->with('user')->where('is_active', true)->where('is_online', true)->whereNull('current_order_id')->get(),
        ]);
    }

    public function store(StoreOrderRequest $request, CreateOrderService $service): RedirectResponse
    {
        // A driver supplied at creation goes through AssignDriverService
        // internally, which can reject it (inactive driver/user) the same
        // way the dedicated assign() action can — same graceful error
        // here instead of an uncaught 500.
        try {
            $order = $service->create($request->validated(), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['driver_id' => $e->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('status', 'Order created.');
    }

    public function show(Order $order, OrderTransitionService $transitions): View
    {
        Gate::authorize('view', $order);

        return view('admin.orders.show', [
            'order' => $order->load(['customer', 'driver.user', 'earningSetBy', 'statusHistories.changedBy', 'offers.driver.user']),
            // Mirrors AssignDriverService's own eligibility checks (spec
            // §14) — no point offering a driver in the picker who'd just
            // be rejected on submit.
            'availableDrivers' => Driver::query()
                ->with('user')
                ->where('is_active', true)
                ->where('is_online', true)
                ->whereNull('current_order_id')
                ->get(),
            'allowedTransitions' => $transitions->allowedTransitions($order->status),
        ]);
    }

    public function edit(Order $order): View
    {
        Gate::authorize('update', $order);

        return view('admin.orders.edit', [
            'order' => $order,
            'locked' => ! in_array($order->status, [OrderStatus::PENDING, OrderStatus::AVAILABLE], true),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order, UpdateOrderService $service): RedirectResponse
    {
        $service->update($order, $request->validated(), $request->user());

        return redirect()->route('admin.orders.show', $order)->with('status', 'Order updated.');
    }

    /**
     * Manual driver assignment (spec §12) — active driver + active driver
     * user only, enforced in AssignDriverService regardless of what the
     * client sends.
     */
    public function assign(Request $request, Order $order, AssignDriverService $service): RedirectResponse
    {
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);

        $driver = Driver::findOrFail($validated['driver_id']);

        try {
            $service->assign($order, $driver, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['driver_id' => $e->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('status', 'Driver assigned.');
    }

    /**
     * Operational status transitions — the only way `orders.status` ever
     * changes. `to_status` is validated as a real OrderStatus value
     * before it ever reaches OrderTransitionService; never an arbitrary
     * client-supplied string.
     */
    public function transition(Request $request, Order $order, OrderTransitionService $service): RedirectResponse
    {
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'to_status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->transition(
                $order,
                OrderStatus::from($validated['to_status']),
                $request->user(),
                $validated['note'] ?? null,
            );
        } catch (InvalidOrderTransitionException $e) {
            return back()->withErrors(['to_status' => $e->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('status', 'Order status updated.');
    }
}
