<?php

namespace Tests\Feature\Admin;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuItemManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(MenuCategory $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Cheeseburger',
            'description' => 'Beef patty, cheddar, pickles.',
            'price' => '9.50',
            'menu_category_id' => $category->id,
        ], $overrides);
    }

    public function test_super_admin_can_crud_menu_items(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($admin)->get(route('admin.restaurants.menu-items.create', $restaurant))->assertOk();

        $create = $this->actingAs($admin)->post(route('admin.restaurants.menu-items.store', $restaurant), $this->validPayload($category));
        $create->assertRedirect(route('admin.restaurants.show', $restaurant));
        $item = MenuItem::where('name', 'Cheeseburger')->firstOrFail();
        $this->assertSame($restaurant->id, $item->restaurant_id);
        $this->assertSame('9.50', (string) $item->price);

        $this->actingAs($admin)->get(route('admin.menu-items.edit', $item))->assertOk();

        $this->actingAs($admin)->put(route('admin.menu-items.update', $item), $this->validPayload($category, ['name' => 'Double Cheeseburger']))
            ->assertRedirect(route('admin.restaurants.show', $restaurant));
        $this->assertSame('Double Cheeseburger', $item->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.menu-items.destroy', $item))
            ->assertRedirect(route('admin.restaurants.show', $restaurant));
        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_dispatcher_can_crud_menu_items(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);

        $create = $this->actingAs($dispatcher)->post(route('admin.restaurants.menu-items.store', $restaurant), $this->validPayload($category));
        $create->assertRedirect();
        $item = MenuItem::where('name', 'Cheeseburger')->firstOrFail();

        $this->actingAs($dispatcher)->put(route('admin.menu-items.update', $item), $this->validPayload($category, ['price' => '11.00']))->assertRedirect();
        $this->assertSame('11.00', (string) $item->fresh()->price);

        $this->actingAs($dispatcher)->delete(route('admin.menu-items.destroy', $item))->assertRedirect();
        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_driver_cannot_access_menu_item_management(): void
    {
        $driver = User::factory()->driver()->create();
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id]);

        $this->actingAs($driver)->get(route('admin.restaurants.menu-items.create', $restaurant))->assertForbidden();
        $this->actingAs($driver)->post(route('admin.restaurants.menu-items.store', $restaurant), $this->validPayload($category))->assertForbidden();
        $this->actingAs($driver)->get(route('admin.menu-items.edit', $item))->assertForbidden();
        $this->actingAs($driver)->put(route('admin.menu-items.update', $item), $this->validPayload($category))->assertForbidden();
        $this->actingAs($driver)->delete(route('admin.menu-items.destroy', $item))->assertForbidden();
    }

    public function test_menu_item_requires_a_name_and_price(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->actingAs($admin)->post(route('admin.restaurants.menu-items.store', $restaurant), $this->validPayload($category, [
            'name' => '',
            'price' => '',
        ]));

        $response->assertSessionHasErrors(['name', 'price']);
        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_menu_item_category_must_belong_to_its_restaurant(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create();
        $otherRestaurant = Restaurant::factory()->create();
        $categoryFromOtherRestaurant = MenuCategory::factory()->create(['restaurant_id' => $otherRestaurant->id]);

        $response = $this->actingAs($admin)->post(
            route('admin.restaurants.menu-items.store', $restaurant),
            $this->validPayload($categoryFromOtherRestaurant),
        );

        $response->assertSessionHasErrors('menu_category_id');
        $this->assertDatabaseCount('menu_items', 0);
    }

    // --- Photo upload ---------------------------------------------------

    public function test_menu_item_photo_can_be_uploaded_on_create(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($admin)->post(route('admin.restaurants.menu-items.store', $restaurant), $this->validPayload($category, [
            'photo' => UploadedFile::fake()->image('burger.jpg'),
        ]))->assertRedirect();

        $item = MenuItem::where('name', 'Cheeseburger')->firstOrFail();
        $this->assertNotNull($item->photo_path);
        Storage::disk('public')->assertExists($item->photo_path);
    }

    public function test_menu_item_photo_can_be_replaced(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'photo_path' => 'menu-items/old.jpg',
        ]);
        Storage::disk('public')->put('menu-items/old.jpg', 'fake-content');

        $this->actingAs($admin)->put(route('admin.menu-items.update', $item), $this->validPayload($category, [
            'photo' => UploadedFile::fake()->image('new.jpg'),
        ]))->assertRedirect();

        $item->refresh();
        $this->assertNotSame('menu-items/old.jpg', $item->photo_path);
        Storage::disk('public')->assertMissing('menu-items/old.jpg');
        Storage::disk('public')->assertExists($item->photo_path);
    }

    public function test_menu_item_photo_can_be_removed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'photo_path' => 'menu-items/old.jpg',
        ]);
        Storage::disk('public')->put('menu-items/old.jpg', 'fake-content');

        $this->actingAs($admin)->put(route('admin.menu-items.update', $item), $this->validPayload($category, [
            'remove_photo' => '1',
        ]))->assertRedirect();

        $this->assertNull($item->fresh()->photo_path);
        Storage::disk('public')->assertMissing('menu-items/old.jpg');
    }

    public function test_menu_item_photo_upload_rejects_a_non_image_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->actingAs($admin)->post(route('admin.restaurants.menu-items.store', $restaurant), $this->validPayload($category, [
            'photo' => UploadedFile::fake()->create('not-an-image.pdf', 10),
        ]));

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('menu_items', 0);
    }
}
