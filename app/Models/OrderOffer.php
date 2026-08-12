<?php

namespace App\Models;

use App\Enums\OrderOfferResult;
use Database\Factories\OrderOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (order, driver) offer. Written by
 * App\Services\Orders\OfferOrderService (creation), RespondToOrderOfferService
 * (accept/reject), and ExpireOrderOfferJob (timeout) — never directly by a
 * controller.
 */
#[Fillable(['order_id', 'driver_id', 'offered_at', 'responded_at', 'result'])]
class OrderOffer extends Model
{
    /** @use HasFactory<OrderOfferFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => OrderOfferResult::class,
            'offered_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
