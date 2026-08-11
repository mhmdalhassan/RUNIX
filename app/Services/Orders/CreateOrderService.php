<?php

namespace App\Services\Orders;

use App\Enums\FeePayer;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates order creation end to end (spec §15) inside one
 * transaction — validate customer/driver/money, generate the order
 * number + tracking token, create the order in PENDING, record its
 * initial history, then assign the driver if one was supplied. If any
 * step fails, nothing commits: no partial order is ever left behind.
 */
class CreateOrderService
{
    public function __construct(
        private readonly OrderNumberGenerator $numbers,
        private readonly OrderTransitionService $transitions,
        private readonly AssignDriverService $assignDriver,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Already validated by StoreOrderRequest.
     */
    public function create(array $data, User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor) {
            $customer = Customer::findOrFail($data['customer_id']);

            // COD fields are never trusted from the client while the
            // feature is off — forced to 0 here regardless of what was
            // submitted, not just hidden in the UI.
            $codEnabled = (bool) config('runix.cod_enabled');

            $order = Order::create([
                'order_number' => $this->numbers->generate(),
                'tracking_token' => $this->numbers->generateUniqueTrackingToken(),

                'customer_id' => $customer->id,
                'customer_name_snapshot' => $customer->name,
                'customer_phone_snapshot' => $customer->phone,

                'pickup_address' => $data['pickup_address'],
                'pickup_latitude' => $data['pickup_latitude'] ?? null,
                'pickup_longitude' => $data['pickup_longitude'] ?? null,

                'delivery_address' => $data['delivery_address'],
                'delivery_latitude' => $data['delivery_latitude'] ?? null,
                'delivery_longitude' => $data['delivery_longitude'] ?? null,

                'delivery_fee' => $data['delivery_fee'],
                'merchant_amount' => $codEnabled ? ($data['merchant_amount'] ?? 0) : 0,
                'cod_amount' => $codEnabled ? ($data['cod_amount'] ?? 0) : 0,
                'currency' => 'USD',
                'fee_payer' => $data['fee_payer'] ?? FeePayer::CUSTOMER,

                'driver_earning' => $data['driver_earning'],
                'driver_earning_override' => $data['driver_earning_override'] ?? false,
                'driver_earning_override_reason' => $data['driver_earning_override_reason'] ?? null,
                'driver_earning_set_by' => $actor->id,

                'payment_method' => $data['payment_method'] ?? PaymentMethod::CASH,
                'payment_status' => $data['payment_status'] ?? PaymentStatus::PENDING,

                'customer_notes' => $data['customer_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
            ]);

            // `status` is deliberately not mass-assignable (see Order's
            // #[Fillable] list) and is left out of the array above, so it
            // was only ever set by the `orders.status` column DEFAULT —
            // Eloquent doesn't know that happened until we refresh, and
            // recordInitial() below needs the real value, not an
            // in-memory null.
            $order->refresh();

            $this->transitions->recordInitial($order, $actor);

            if (! empty($data['driver_id'])) {
                $driver = Driver::findOrFail($data['driver_id']);
                $order = $this->assignDriver->assign($order, $driver, $actor);
            }

            return $order->fresh();
        });
    }
}
