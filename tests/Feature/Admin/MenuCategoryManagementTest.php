<?php

namespace Tests\Feature\Admin;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_crud_menu_categories(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create();

        $this->actingAs($admin)->get(route('admin.restaurants.menu-categories.create', $restaurant))->assertOk();

        $create = $this->actingAs($admin)->post(route('admin.restaurants.menu-categories.store', $restaurant), ['name' => 'Burgers']);
        $create->assertRedirect(route('admin.restaurants.show', $restaurant));
        $category = MenuCategory::where('name', 'Burgers')->firstOrFail();
        $this->assertSame($restaurant->id, $category->restaurant_id);

        $this->actingAs($admin)->get(route('admin.menu-categories.edit', $category))->assertOk();

        $this->actingAs($admin)->put(route('admin.menu-categories.update', $category), ['name' => 'Burgers & Fries'])
            ->assertRedirect(route('admin.restaurants.show', $restaurant));
        $this->assertSame('Burgers & Fries', $category->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.menu-categories.destroy', $category))
            ->assertRedirect(route('admin.restaurants.show', $restaurant));
        $this->assertDatabaseMissing('menu_categories', ['id' => $category->id]);
    }

    public function test_dispatcher_can_crud_menu_categories(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $restaurant = Restaurant::factory()->create();

        $create = $this->actingAs($dispatcher)->post(route('admin.restaurants.menu-categories.store', $restaurant), ['name' => 'Drinks']);
        $create->assertRedirect();
        $category = MenuCategory::where('name', 'Drinks')->firstOrFail();

        $this->actingAs($dispatcher)->put(route('admin.menu-categories.update', $category), ['name' => 'Beverages'])->assertRedirect();
        $this->assertSame('Beverages', $category->fresh()->name);

        $this->actingAs($dispatcher)->delete(route('admin.menu-categories.destroy', $category))->assertRedirect();
        $this->assertDatabaseMissing('menu_categories', ['id' => $category->id]);
    }

    public function test_driver_cannot_access_menu_category_management(): void
    {
        $driver = User::factory()->driver()->create();
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($driver)->get(route('admin.restaurants.menu-categories.create', $restaurant))->assertForbidden();
        $this->actingAs($driver)->post(route('admin.restaurants.menu-categories.store', $restaurant), ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($driver)->get(route('admin.menu-categories.edit', $category))->assertForbidden();
        $this->actingAs($driver)->put(route('admin.menu-categories.update', $category), ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($driver)->delete(route('admin.menu-categories.destroy', $category))->assertForbidden();
    }

    public function test_menu_category_requires_a_name(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.restaurants.menu-categories.store', $restaurant), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('menu_categories', 0);
    }

    public function test_deleting_a_category_cascades_to_its_items(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
        ]);

        $this->actingAs($admin)->delete(route('admin.menu-categories.destroy', $category))->assertRedirect();

        $this->assertDatabaseMissing('menu_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }
}
