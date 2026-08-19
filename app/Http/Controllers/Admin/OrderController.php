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
use App\Services\Geo\HaversineDistanceCalculator;
use App\Services\Orders\AssignDriverService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\OrderTransitionService;
use App\Services\Orders\UpdateOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
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

        // No longer the full active-customer list (didn't scale, no way
        // to jump to a phone number) — the view searches
        // Admin\CustomerController::search() instead. Only look up the
        // previously-chosen customer, and only to survive a validation
        // failure on some other field of this same form without losing
        // that choice.
        $selectedCustomerId = old('customer_id');

        return view('admin.orders.create', [
            'selectedCustomer' => $selectedCustomerId
                ? Customer::query()->find($selectedCustomerId, ['id', 'name', 'phone'])
                : null,
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

    public function show(Order $order, OrderTransitionService $transitions, HaversineDistanceCalculator $distanceCalculator): View
    {
        Gate::authorize('view', $order);

        // Mirrors AssignDriverService's own eligibility checks (spec
        // §14) — no point offering a driver in the picker who'd just
        // be rejected on submit.
        $availableDrivers = Driver::query()
            ->with('user')
            ->where('is_active', true)
            ->where('is_online', true)
            ->whereNull('current_order_id')
            ->get();

        return view('admin.orders.show', [
            'order' => $order->load(['customer', 'driver.user', 'earningSetBy', 'statusHistories.changedBy', 'offers.driver.user']),
            'availableDrivers' => $availableDrivers,
            'driverDistancesKm' => $this->driverDistancesKm($order, $availableDrivers, $distanceCalculator),
            'allowedTransitions' => $transitions->allowedTransitions($order->status),
        ]);
    }

    /**
     * Phase 6 §5 — display-only distance from the order's pickup point to
     * each driver in the Assign Driver picker. Deliberately independent of
     * App\Services\Orders\EligibleDriverFinder: it never excludes/reorders
     * anyone, it just annotates the same list the picker already shows.
     * Reuses the one Haversine implementation (Phase 5 §2) rather than
     * duplicating the math.
     *
     * @param  Collection<int, Driver>  $drivers
     * @return array<int, float|null> Driver id => distance in km, or null
     *                                when it isn't computable (no pickup
     *                                coordinates on the order, or no
     *                                last-known location on the driver).
     */
    private function driverDistancesKm(Order $order, Collection $drivers, HaversineDistanceCalculator $distanceCalculator): array
    {
        if ($order->pickup_latitude === null || $order->pickup_longitude === null) {
            return [];
        }

        return $drivers->mapWithKeys(function (Driver $driver) use ($order, $distanceCalculator) {
            if ($driver->last_latitude === null || $driver->last_longitude === null) {
                return [$driver->id => null];
            }

            return [$driver->id => $distanceCalculator->distanceInKm(
                (float) $order->pickup_latitude,
                (float) $order->pickup_longitude,
                (float) $driver->last_latitude,
                (float) $driver->last_longitude,
            )];
        })->all();
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
