<?php

namespace Tests\Feature\Admin;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurantManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Cedar Grill',
            'phone' => '+9613123456',
            'address' => '123 Cedar St, Beirut',
        ], $overrides);
    }

    public function test_super_admin_can_crud_restaurants(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.restaurants.index'))->assertOk();

        $create = $this->actingAs($admin)->post(route('admin.restaurants.store'), $this->validPayload());
        $create->assertRedirect();
        $restaurant = Restaurant::where('name', 'Cedar Grill')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.restaurants.show', $restaurant))->assertOk();

        $this->actingAs($admin)->put(route('admin.restaurants.update', $restaurant), $this->validPayload(['name' => 'Cedar Grill Updated']))
            ->assertRedirect();
        $this->assertSame('Cedar Grill Updated', $restaurant->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.restaurants.destroy', $restaurant))->assertRedirect();
        $this->assertDatabaseMissing('restaurants', ['id' => $restaurant->id]);
    }

    public function test_dispatcher_can_crud_restaurants(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $create = $this->actingAs($dispatcher)->post(route('admin.restaurants.store'), $this->validPayload());
        $create->assertRedirect();
        $restaurant = Restaurant::where('name', 'Cedar Grill')->firstOrFail();

        $this->actingAs($dispatcher)->put(route('admin.restaurants.update', $restaurant), $this->validPayload(['name' => 'Cedar Grill V2']))
            ->assertRedirect();
        $this->assertSame('Cedar Grill V2', $restaurant->fresh()->name);

        $this->actingAs($dispatcher)->delete(route('admin.restaurants.destroy', $restaurant))->assertRedirect();
        $this->assertDatabaseMissing('restaurants', ['id' => $restaurant->id]);
    }

    public function test_driver_cannot_access_restaurant_management(): void
    {
        $driver = User::factory()->driver()->create();
        $restaurant = Restaurant::factory()->create();

        $this->actingAs($driver)->get(route('admin.restaurants.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('admin.restaurants.create'))->assertForbidden();
        $this->actingAs($driver)->post(route('admin.restaurants.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($driver)->get(route('admin.restaurants.show', $restaurant))->assertForbidden();
        $this->actingAs($driver)->get(route('admin.restaurants.edit', $restaurant))->assertForbidden();
        $this->actingAs($driver)->put(route('admin.restaurants.update', $restaurant), $this->validPayload())->assertForbidden();
        $this->actingAs($driver)->delete(route('admin.restaurants.destroy', $restaurant))->assertForbidden();
    }

    public function test_restaurant_requires_a_name(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.restaurants.store'), $this->validPayload(['name' => '']));

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('restaurants', 0);
    }

    // --- Logo upload ---------------------------------------------------

    public function test_restaurant_logo_can_be_uploaded_on_create(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.restaurants.store'), $this->validPayload([
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]))->assertRedirect();

        $restaurant = Restaurant::where('name', 'Cedar Grill')->firstOrFail();
        $this->assertNotNull($restaurant->logo_path);
        Storage::disk('public')->assertExists($restaurant->logo_path);
    }

    public function test_restaurant_logo_can_be_replaced(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create(['logo_path' => 'restaurants/old.jpg']);
        Storage::disk('public')->put('restaurants/old.jpg', 'fake-content');

        $this->actingAs($admin)->put(route('admin.restaurants.update', $restaurant), $this->validPayload([
            'logo' => UploadedFile::fake()->image('new-logo.jpg'),
        ]))->assertRedirect();

        $restaurant->refresh();
        $this->assertNotSame('restaurants/old.jpg', $restaurant->logo_path);
        Storage::disk('public')->assertMissing('restaurants/old.jpg');
        Storage::disk('public')->assertExists($restaurant->logo_path);
    }

    public function test_restaurant_logo_can_be_removed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $restaurant = Restaurant::factory()->create(['logo_path' => 'restaurants/old.jpg']);
        Storage::disk('public')->put('restaurants/old.jpg', 'fake-content');

        $this->actingAs($admin)->put(route('admin.restaurants.update', $restaurant), $this->validPayload([
            'remove_logo' => '1',
        ]))->assertRedirect();

        $this->assertNull($restaurant->fresh()->logo_path);
        Storage::disk('public')->assertMissing('restaurants/old.jpg');
    }

    public function test_restaurant_logo_upload_rejects_a_non_image_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.restaurants.store'), $this->validPayload([
            'logo' => UploadedFile::fake()->create('not-an-image.pdf', 10),
        ]));

        $response->assertSessionHasErrors('logo');
        $this->assertDatabaseCount('restaurants', 0);
    }
}
