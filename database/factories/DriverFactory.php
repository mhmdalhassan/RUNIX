<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::DRIVER]),
            'phone' => fake()->unique()->numerify('+9617#######'),
            'is_active' => true,
            'is_online' => false,
            'last_seen_at' => null,
            'last_latitude' => null,
            'last_longitude' => null,
            'last_accuracy' => null,
            'last_location_at' => null,
        ];
    }

    /**
     * Indicate that the driver is currently online.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => true,
            'last_seen_at' => now(),
        ]);
    }
}
