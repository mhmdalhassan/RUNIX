<?php

namespace App\Models;

use App\Enums\FeePayer;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status is never written directly by a controller/form — every change
 * goes through App\Services\Orders\OrderTransitionService, which is also
 * responsible for keeping the `*_at` timestamp columns and
 * order_status_histories in sync with it.
 */
#[Fillable([
    'order_number', 'tracking_token',
    'customer_id', 'customer_name_snapshot', 'customer_phone_snapshot',
    'pickup_address', 'pickup_latitude', 'pickup_longitude',
    'delivery_address', 'delivery_latitude', 'delivery_longitude',
    'driver_id',
    'delivery_fee', 'merchant_amount', 'cod_amount', 'currency', 'fee_payer',
    'driver_earning', 'driver_earning_override', 'driver_earning_override_reason', 'driver_earning_set_by',
    'payment_method', 'payment_status',
    'distance_km', 'estimated_minutes',
    'customer_notes', 'internal_notes',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'fee_payer' => FeePayer::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'driver_earning_override' => 'boolean',
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'delivery_latitude' => 'decimal:7',
            'delivery_longitude' => 'decimal:7',
            'delivery_fee' => 'decimal:2',
            'merchant_amount' => 'decimal:2',
            'cod_amount' => 'decimal:2',
            'driver_earning' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * The staff member who set/last approved the driver_earning value —
     * part of making that figure auditable per §5.
     *
     * @return BelongsTo<User, $this>
     */
    public function earningSetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_earning_set_by');
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('created_at');
    }

    /**
     * Orders that haven't reached a terminal status yet.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (OrderStatus $status) => $status->value,
            OrderStatus::activeStatuses(),
        ));
    }
}
