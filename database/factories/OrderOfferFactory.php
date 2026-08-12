<?php

namespace Database\Factories;

use App\Enums\OrderOfferResult;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderOffer>
 */
class OrderOfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->available(),
            'driver_id' => Driver::factory(),
            'offered_at' => now(),
            'responded_at' => null,
            'result' => OrderOfferResult::PENDING,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'result' => OrderOfferResult::ACCEPTED,
            'responded_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'result' => OrderOfferResult::REJECTED,
            'responded_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'result' => OrderOfferResult::EXPIRED,
            'responded_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'result' => OrderOfferResult::CANCELLED,
            'responded_at' => now(),
        ]);
    }
}
