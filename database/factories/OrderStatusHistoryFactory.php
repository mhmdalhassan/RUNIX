<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from_status' => null,
            'to_status' => fn (array $attributes) => Order::find($attributes['order_id'])?->status,
            'changed_by' => null,
            'note' => null,
        ];
    }
}
