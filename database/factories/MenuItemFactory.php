<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Only menu_category_id is set here — restaurant_id is
            // back-filled in configure() below from whichever category
            // actually ends up resolved (the lazy default one, or an
            // override the caller passed), so the two never disagree the
            // way they would if both were independent factory() calls.
            'menu_category_id' => MenuCategory::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomFloat(2, 3, 40),
            'photo_path' => null,
            'is_available' => true,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (MenuItem $item) {
            if ($item->restaurant_id === null && $item->menu_category_id !== null) {
                $item->restaurant_id = MenuCategory::find($item->menu_category_id)?->restaurant_id;
            }
        });
    }
}
