<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The current password being used by the withAccount() state.
     */
    protected static ?string $password;

    /**
     * Define the model's default state — an "unclaimed" (no login) row,
     * matching what Admin\CustomerController actually creates: a
     * dispatcher entering a phone-order customer, never a self-registered
     * one. Use withAccount() for a row that already has real login
     * credentials. `email` is unique() (not optional()) since it's now
     * a unique DB column — see the customers-auth migration.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('+9613#######'),
            'address' => null,
            'email' => fake()->unique()->safeEmail(),
            'password' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }

    /**
     * A customer who has actually registered — real hashed password +
     * verified email, the "claimed" state
     * App\Services\Customers\CompleteCustomerProfileService looks for the
     * absence of.
     */
    public function withAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => static::$password ??= Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }
}
