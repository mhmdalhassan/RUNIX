<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'phone', 'is_active', 'is_online', 'current_order_id',
    'last_seen_at', 'last_latitude', 'last_longitude', 'last_accuracy', 'last_location_at',
])]
class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_latitude' => 'decimal:7',
            'last_longitude' => 'decimal:7',
            'last_accuracy' => 'decimal:2',
            'last_location_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<OrderOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(OrderOffer::class);
    }

    /**
     * The one order this driver is currently occupied with, if any.
     *
     * Phase 4 note: Phase 3 deliberately had no busy concept ("a driver
     * may hold multiple simultaneous active orders"). `current_order_id`
     * (nullable, unique) reverses that on purpose — as of the offer/accept
     * workflow, a driver can hold at most one order at a time, enforced by
     * the database, not just this relation. See
     * App\Services\Orders\ClaimOrderForDriverService for the atomic claim
     * and OrderTransitionService::transition() for where it's released
     * again on delivery/cancellation/failure.
     *
     * @return BelongsTo<Order, $this>
     */
    public function currentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'current_order_id');
    }

    /**
     * Number of orders currently active for this driver. In practice this
     * is 0 or 1 as of Phase 4 (see currentOrder()'s docblock) — kept as a
     * real count query rather than hardcoded to `currentOrder() !== null`
     * so it stays correct if that constraint is ever relaxed again.
     */
    public function activeOrderCount(): int
    {
        return $this->orders()->active()->count();
    }

    /**
     * Per-day delivered-order count + earnings, most recent day first —
     * the driver's own delivery history, shown on their dashboard and on
     * the admin driver detail page. Callers paginate the result
     * themselves (Laravel's paginate() already counts GROUP BY queries
     * correctly, wrapping them rather than miscounting per-group rows).
     * Read-only reporting over data that already exists — no new schema,
     * no relation to the deferred driver earnings ledger/settlement work.
     *
     * @return HasMany<Order, $this>
     */
    public function deliveryHistoryQuery(): HasMany
    {
        return $this->orders()
            ->selectRaw('DATE(delivered_at) as delivery_date, COUNT(*) as delivered_count, SUM(driver_earning) as earnings_sum')
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereNotNull('delivered_at')
            ->groupBy('delivery_date')
            ->orderByDesc('delivery_date');
    }
}
