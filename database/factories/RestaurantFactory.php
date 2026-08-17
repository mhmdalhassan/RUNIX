<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->numerify('+9613#######'),
            'address' => fake()->address(),
            'pickup_latitude' => null,
            'pickup_longitude' => null,
            'logo_path' => null,
            'is_active' => true,
        ];
    }
}
