<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only. Rows are only ever inserted, exclusively by
 * App\Services\Orders\OrderTransitionService — update()/delete() are
 * overridden below to make that a hard guarantee rather than just a
 * convention nobody enforces. (Cascading delete when the parent Order is
 * removed happens at the database FK level, not through Eloquent, so it's
 * unaffected by the delete() override.)
 */
#[Fillable(['order_id', 'from_status', 'to_status', 'changed_by', 'note'])]
class OrderStatusHistory extends Model
{
    /** @use HasFactory<OrderStatusHistoryFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
            'created_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Order status history is append-only and cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Order status history is append-only and cannot be deleted.');
    }
}
