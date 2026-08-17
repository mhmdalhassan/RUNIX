<?php

namespace App\Services\Orders;

use App\Enums\FeePayer;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates a CUSTOMER's own order end to end — the self-service
 * counterpart to CreateOrderService, which is dispatcher-only. No
 * dispatcher is ever in this loop, so delivery_fee/driver_earning are
 * flat configured defaults (config('runix.customer_ordering')), never
 * user input, and driver_earning_set_by stays null (no staff member set
 * it — same nullable/nullOnDelete column already used for that
 * "unset" state). Publishes straight to AVAILABLE on success, same as
 * CreateOrderService's no-driver-picked branch — every eligible driver
 * sees it on the shared board immediately.
 */
class CreateCustomerOrderService
{
    public function __construct(
        private readonly OrderNumberGenerator $numbers,
        private readonly OrderTransitionService $transitions,
    ) {}

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $cartItems  Already shaped by StoreCustomerOrderRequest.
     *
     * @throws ValidationException if any submitted item no longer belongs
     *                              to this restaurant or is no longer
     *                              available server-side — the cart was
     *                              built client-side against menu state
     *                              that may since have changed, and
     *                              price/availability are never trusted
     *                              from the client (same principle as
     *                              cod_enabled forcing amounts
     *                              server-side elsewhere in this app).
     */
    public function create(Customer $customer, Restaurant $restaurant, array $cartItems, string $deliveryAddress, ?string $customerNotes): Order
    {
        return DB::transaction(function () use ($customer, $restaurant, $cartItems, $deliveryAddress, $customerNotes) {
            $lines = $this->resolveLines($restaurant, $cartItems);

            $merchantAmount = $lines->sum(fn (array $line) => $line['price_snapshot'] * $line['quantity']);
            $deliveryFee = (float) config('runix.customer_ordering.default_delivery_fee');

            $order = Order::create([
                'order_number' => $this->numbers->generate(),
                'tracking_token' => $this->numbers->generateUniqueTrackingToken(),

                'customer_id' => $customer->id,
                'customer_name_snapshot' => $customer->name,
                'customer_phone_snapshot' => $customer->phone,
                'restaurant_id' => $restaurant->id,

                'pickup_address' => $restaurant->address ?? $restaurant->name,
                'pickup_latitude' => $restaurant->pickup_latitude,
                'pickup_longitude' => $restaurant->pickup_longitude,

                // No geocoding/route calculation yet (same deliberate gap
                // as Order's own migration comment) — delivery_latitude/
                // longitude just stay null. EligibleDriverFinder's
                // proximity ranking is soft: a missing location never
                // excludes a driver, it just ranks them last.
                'delivery_address' => $deliveryAddress,
                'delivery_latitude' => null,
                'delivery_longitude' => null,

                'delivery_fee' => $deliveryFee,
                // Independent of config('runix.cod_enabled') — that gate
                // is about the STAFF admin UI not trusting dispatcher-
                // submitted COD input while the feature is unconfirmed
                // for THAT flow. A customer's own order always collects
                // its item total as cash on delivery; this is a
                // different code path with its own validation, not a
                // bypass of that gate.
                'merchant_amount' => $merchantAmount,
                'cod_amount' => $merchantAmount + $deliveryFee,
                'currency' => 'USD',
                'fee_payer' => FeePayer::CUSTOMER,

                'driver_earning' => (float) config('runix.customer_ordering.default_driver_earning'),
                'driver_earning_override' => false,
                'driver_earning_override_reason' => null,
                'driver_earning_set_by' => null,

                'payment_method' => PaymentMethod::CASH,
                'payment_status' => PaymentStatus::PENDING,

                'customer_notes' => $customerNotes,
                'internal_notes' => null,
            ]);

            // `status` is deliberately not mass-assignable (see Order's
            // #[Fillable] list) — same reasoning as CreateOrderService's
            // own refresh() call right after create().
            $order->refresh();

            $this->transitions->recordInitial($order, null);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            $order = $this->transitions->transition($order, OrderStatus::AVAILABLE, null);

            return $order->fresh('items');
        });
    }

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $cartItems
     * @return Collection<int, array{menu_item_id: int, name_snapshot: string, price_snapshot: float, quantity: int}>
     */
    private function resolveLines(Restaurant $restaurant, array $cartItems): Collection
    {
        $quantities = collect($cartItems)->pluck('quantity', 'menu_item_id');

        $menuItems = MenuItem::query()
            ->where('restaurant_id', $restaurant->id)
            ->available()
            ->whereIn('id', $quantities->keys())
            ->get();

        if ($menuItems->count() !== $quantities->count()) {
            throw ValidationException::withMessages([
                'items' => __('Some items in your cart are no longer available. Please review your order.'),
            ]);
        }

        return $menuItems->map(fn (MenuItem $item) => [
            'menu_item_id' => $item->id,
            'name_snapshot' => $item->name,
            'price_snapshot' => (float) $item->price,
            'quantity' => (int) $quantities[$item->id],
        ])->values();
    }
}
