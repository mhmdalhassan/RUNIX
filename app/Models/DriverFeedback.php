<?php

namespace App\Models;

use Database\Factories\DriverFeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A customer's one rating (+ optional comment) on the driver who
 * delivered their order — see App\Services\Customers\
 * SubmitDriverFeedbackService for the only place these are created.
 * Write-once, same reasoning and same enforcement shape as
 * OrderStatusHistory: a review nobody can quietly edit after the fact is
 * worth more than one that can. Cascading delete when the parent Order
 * is removed happens at the database FK level, not through Eloquent, so
 * it's unaffected by the delete() override below.
 */
#[Fillable(['order_id', 'driver_id', 'customer_id', 'rating', 'comment'])]
class DriverFeedback extends Model
{
    /** @use HasFactory<DriverFeedbackFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
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
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Driver feedback is write-once and cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Driver feedback is write-once and cannot be deleted.');
    }
}
