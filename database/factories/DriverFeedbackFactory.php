<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverFeedback;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverFeedback>
 */
class DriverFeedbackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'driver_id' => Driver::factory(),
            'customer_id' => Customer::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
