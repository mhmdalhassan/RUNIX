<?php

namespace Database\Factories;

use App\Enums\FeePayer;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Services\Orders\OrderNumberGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 *
 * Bypasses CreateOrderService (factories are for fast, direct test/seed
 * rows, not exercising the service) but still generates real,
 * collision-safe order numbers/tracking tokens the same way the service
 * does, via the same OrderNumberGenerator.
 */
class OrderFactory extends Factory
{
    /**
     * A structurally valid PENDING order with no driver.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $deliveryFee = fake()->randomFloat(2, 3, 15);

        return [
            'order_number' => app(OrderNumberGenerator::class)->generate(),
            'tracking_token' => app(OrderNumberGenerator::class)->generateUniqueTrackingToken(),

            'customer_id' => Customer::factory(),
            'customer_name_snapshot' => fn (array $attributes) => Customer::find($attributes['customer_id'])?->name ?? fake()->name(),
            'customer_phone_snapshot' => fn (array $attributes) => Customer::find($attributes['customer_id'])?->phone ?? fake()->numerify('+9613#######'),

            'pickup_address' => fake()->address(),
            'pickup_latitude' => null,
            'pickup_longitude' => null,

            'delivery_address' => fake()->address(),
            'delivery_latitude' => null,
            'delivery_longitude' => null,

            'driver_id' => null,
            'assigned_at' => null,
            'accepted_at' => null,
            'picked_up_at' => null,
            'delivered_at' => null,
            'cancelled_at' => null,
            'failed_at' => null,

            'status' => OrderStatus::PENDING,

            'delivery_fee' => $deliveryFee,
            'merchant_amount' => 0,
            'cod_amount' => 0,
            'currency' => 'USD',
            'fee_payer' => FeePayer::CUSTOMER,

            'driver_earning' => round($deliveryFee * 0.7, 2),
            'driver_earning_override' => false,
            'driver_earning_override_reason' => null,
            'driver_earning_set_by' => null,

            'payment_method' => PaymentMethod::CASH,
            'payment_status' => PaymentStatus::PENDING,

            'distance_km' => null,
            'estimated_minutes' => null,

            'customer_notes' => null,
            'internal_notes' => null,
        ];
    }

    public function available(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::AVAILABLE]);
    }

    /**
     * Assigned to an active driver and accepted on their behalf.
     */
    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::ACCEPTED,
            'driver_id' => Driver::factory(),
            'assigned_at' => now()->subMinutes(30),
            'accepted_at' => now()->subMinutes(25),
        ]);
    }

    public function pickedUp(): static
    {
        return $this->accepted()->state(fn () => [
            'status' => OrderStatus::PICKED_UP,
            'picked_up_at' => now()->subMinutes(15),
        ]);
    }

    public function onTheWay(): static
    {
        return $this->pickedUp()->state(fn () => [
            'status' => OrderStatus::ON_THE_WAY,
        ]);
    }

    public function delivered(): static
    {
        return $this->onTheWay()->state(fn () => [
            'status' => OrderStatus::DELIVERED,
            'delivered_at' => now(),
            'payment_status' => PaymentStatus::COLLECTED,
        ]);
    }

    /**
     * Cancelled straight from PENDING — cancellation is legal from every
     * non-terminal status, this is just the simplest representative one.
     */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Failed after being accepted — failure is only legal from
     * ACCEPTED/PICKED_UP/ON_THE_WAY, never from PENDING/AVAILABLE.
     */
    public function failed(): static
    {
        return $this->accepted()->state(fn () => [
            'status' => OrderStatus::FAILED,
            'failed_at' => now(),
        ]);
    }
}
