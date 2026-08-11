<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applies a validated UpdateOrderRequest payload to an existing order.
 * Only touches keys actually present in $data — UpdateOrderRequest's
 * `prohibited` rules on locked fields (spec §16) mean those keys simply
 * aren't present once an order has moved past PENDING/AVAILABLE, so this
 * never needs to know about the lock itself, only about what showed up.
 */
class UpdateOrderService
{
    /**
     * @param  array<string, mixed>  $data  Already validated by UpdateOrderRequest.
     */
    public function update(Order $order, array $data, User $actor): Order
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            if (array_key_exists('driver_earning', $data)) {
                $data['driver_earning_set_by'] = $actor->id;
            }

            // Defense in depth: UpdateOrderRequest already makes these
            // `prohibited` (so absent) whenever COD is disabled, but never
            // trust client input for money fields on a second path either.
            if (! config('runix.cod_enabled')) {
                unset($data['merchant_amount'], $data['cod_amount']);
            }

            $order->update($data);

            return $order->refresh();
        });
    }
}
