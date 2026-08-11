<?php

namespace App\Models;

use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'phone', 'is_active', 'is_online', 'last_seen_at', 'last_latitude', 'last_longitude', 'last_accuracy', 'last_location_at'])]
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
     * Number of orders currently active for this driver. A driver may
     * hold multiple simultaneous active orders — there is no
     * `current_order_id`/busy flag anywhere in the schema, by design.
     */
    public function activeOrderCount(): int
    {
        return $this->orders()->active()->count();
    }
}
