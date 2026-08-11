<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Generates the two customer-facing order identifiers. Never derived from
 * `orders.id` and never `Order::count() + 1` — that's race-prone under
 * concurrent creation. Must be called from inside the same DB transaction
 * as the order insert: generate()'s `lockForUpdate()` row lock is what
 * makes concurrent creation safe.
 */
class OrderNumberGenerator
{
    /**
     * RUN-YYYYMMDD-0001, sequential per calendar day via a dedicated
     * order_number_sequences row (see its migration).
     */
    public function generate(): string
    {
        $dateKey = now()->toDateString();

        // Guarantees the row exists without erroring if a concurrent
        // transaction inserted it first.
        DB::table('order_number_sequences')->insertOrIgnore(['date_key' => $dateKey, 'next_number' => 0]);

        // lockForUpdate() takes an InnoDB row lock on this date's counter,
        // serializing concurrent creators — the second transaction simply
        // waits here until the first commits.
        $current = (int) DB::table('order_number_sequences')
            ->where('date_key', $dateKey)
            ->lockForUpdate()
            ->value('next_number');

        $sequence = $current + 1;

        DB::table('order_number_sequences')
            ->where('date_key', $dateKey)
            ->update(['next_number' => $sequence]);

        return sprintf('RUN-%s-%04d', now()->format('Ymd'), $sequence);
    }

    /**
     * A cryptographically secure (random_bytes-backed) 40-character
     * tracking token. Collisions are astronomically unlikely at this
     * length, but this still regenerates on a collision as defense in
     * depth — the `orders.tracking_token` unique index is the actual
     * guarantee.
     */
    public function generateUniqueTrackingToken(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = Str::random(40);

            if (! Order::where('tracking_token', $token)->exists()) {
                return $token;
            }
        }

        throw new RuntimeException('Could not generate a unique tracking token after 5 attempts.');
    }
}
