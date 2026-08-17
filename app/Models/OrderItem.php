<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a customer-placed Order — created only by
 * App\Services\Orders\CreateCustomerOrderService, never by the
 * dispatcher-facing Admin\OrderController (a dispatcher order has no
 * items, just a flat delivery job). name_snapshot/price_snapshot
 * preserve exactly what was ordered even if the MenuItem is later
 * edited, marked unavailable, or deleted (menu_item_id is nullable +
 * nullOnDelete — see the migration).
 */
#[Fillable(['order_id', 'menu_item_id', 'name_snapshot', 'price_snapshot', 'quantity'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'quantity' => 'integer',
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
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function subtotal(): float
    {
        return (float) $this->price_snapshot * $this->quantity;
    }
}
