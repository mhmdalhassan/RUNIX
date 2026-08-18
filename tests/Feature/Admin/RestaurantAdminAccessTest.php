<?php

namespace Tests\Feature\Admin;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RESTAURANT_ADMIN — a staff role scoped to exactly one restaurant
 * (User::restaurant_id), managing only that restaurant's own menu and
 * profile. Dispatcher/Super Admin keep full access to every restaurant
 * unchanged (see RestaurantPolicy/MenuCategoryPolicy/MenuItemPolicy) —
 * this role is additive, not exclusive. See also StaffManagementTest for
 * account creation and MenuCategoryManagementTest/MenuItemManagementTest/
 * RestaurantManagementTest for the Dispatcher/Super Admin coverage this
 * doesn't repeat.
 */
class RestaurantAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_restaurant_admin_can_view_and_update_their_own_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Cedar Grill']);
        $admin = User::factory()->restaurantAdmin($restaurant->id)->create();

        $this->actingAs($admin)->get(route('admin.restaurants.show', $restaurant))->assertOk();
        $this->actingAs($admin)->get(route('admin.restaurants.edit', $restaurant))->assertOk();

        $this->actingAs($admin)->put(route('admin.restaurants.update', $restaurant), [
            'name' => 'Cedar Grill Updated',
            'opens_at' => '09:00',
            'closes_at' => '22:00',
        ])->assertRedirect(route('admin.restaurants.show', $restaurant));

        $this->assertSame('Cedar Grill Updated', $restaurant->fresh()->name);
    }

    public function test_a_restaurant_admin_cannot_view_or_update_a_different_restaurant(): void
    {
        $own = Restaurant::factory()->create();
        $other = Restaurant::factory()->create(['name' => 'Someone Else\'s Place']);
        $admin = User::factory()->restaurantAdmin($own->id)->create();

        $this->actingAs($admin)->get(route('admin.restaurants.show', $other))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.restaurants.edit', $other))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.restaurants.update', $other), ['name' => 'Hijacked'])->assertForbidden();
        $this->assertNotSame('Hijacked', $other->fresh()->name);
    }

    public function test_a_restaurant_admin_cannot_list_all_restaurants_create_new_ones_or_delete_their_own(): void
    {
        $restaurant = Restaurant::factory()->create();
        $admin = User::factory()->restaurantAdmin($restaurant->id)->create();

        $this->actingAs($admin)->get(route('admin.restaurants.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.restaurants.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.restaurants.store'), ['name' => 'New Spot'])->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.restaurants.destroy', $restaurant))->assertForbidden();
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id]);
    }

    public function test_a_restaurant_admin_can_crud_their_own_restaurants_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $admin = User::factory()->restaurantAdmin($restaurant->id)->create();

        $create = $this->actingAs($admin)->post(route('admin.restaurants.menu-categories.store', $restaurant), ['name' => 'Mains']);
        $create->assertRedirect(route('admin.restaurants.show', $restaurant));
        $category = MenuCategory::where('name', 'Mains')->firstOrFail();

        $storeItem = $this->actingAs($admin)->post(route('admin.restaurants.menu-items.store', $restaurant), [
            'name' => 'Cheeseburger',
            'price' => 9.5,
            'menu_category_id' => $category->id,
        ]);
        $storeItem->assertRedirect(route('admin.restaurants.show', $restaurant));
        $item = MenuItem::where('name', 'Cheeseburger')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.menu-items.update', $item), [
            'name' => 'Double Cheeseburger',
            'price' => 12,
            'menu_category_id' => $category->id,
        ])->assertRedirect();
        $this->assertSame('Double Cheeseburger', $item->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.menu-items.destroy', $item))->assertRedirect();
        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);

        $this->actingAs($admin)->delete(route('admin.menu-categories.destroy', $category))->assertRedirect();
        $this->assertDatabaseMissing('menu_categories', ['id' => $category->id]);
    }

    public function test_a_restaurant_admin_cannot_touch_a_different_restaurants_menu(): void
    {
        $own = Restaurant::factory()->create();
        $other = Restaurant::factory()->create();
        $otherCategory = MenuCategory::factory()->create(['restaurant_id' => $other->id]);
        $otherItem = MenuItem::factory()->create(['restaurant_id' => $other->id, 'menu_category_id' => $otherCategory->id]);
        $admin = User::factory()->restaurantAdmin($own->id)->create();

        $this->actingAs($admin)->get(route('admin.restaurants.menu-categories.create', $other))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.restaurants.menu-categories.store', $other), ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($admin)->put(route('admin.menu-categories.update', $otherCategory), ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.menu-categories.destroy', $otherCategory))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.restaurants.menu-items.create', $other))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.menu-items.update', $otherItem), [
            'name' => 'Nope',
            'price' => 1,
            'menu_category_id' => $otherCategory->id,
        ])->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.menu-items.destroy', $otherItem))->assertForbidden();

        $this->assertDatabaseHas('menu_categories', ['id' => $otherCategory->id]);
        $this->assertDatabaseHas('menu_items', ['id' => $otherItem->id]);
    }

    public function test_a_restaurant_admin_cannot_reach_driver_customer_or_order_management(): void
    {
        $restaurant = Restaurant::factory()->create();
        $admin = User::factory()->restaurantAdmin($restaurant->id)->create();

        $this->actingAs($admin)->get(route('admin.drivers.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.customers.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_dispatcher_and_super_admin_keep_full_access_to_a_restaurant_that_has_its_own_admin(): void
    {
        $restaurant = Restaurant::factory()->create();
        User::factory()->restaurantAdmin($restaurant->id)->create();

        $dispatcher = User::factory()->dispatcher()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($dispatcher)->get(route('admin.restaurants.show', $restaurant))->assertOk();
        $this->actingAs($dispatcher)->put(route('admin.restaurants.update', $restaurant), ['name' => 'Dispatcher Edit'])->assertRedirect();
        $this->assertSame('Dispatcher Edit', $restaurant->fresh()->name);

        $this->actingAs($superAdmin)->delete(route('admin.restaurants.destroy', $restaurant))->assertRedirect();
        $this->assertDatabaseMissing('restaurants', ['id' => $restaurant->id]);
    }

    public function test_the_dashboard_route_sends_a_restaurant_admin_straight_to_their_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();
        $admin = User::factory()->restaurantAdmin($restaurant->id)->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertRedirect(route('admin.restaurants.show', $restaurant));
    }

    // --- Status preview (the "which day am I looking at" preference) --

    public function test_the_day_picker_is_not_shown_to_a_restaurant_admin(): void
    {
        $restaurant = Restaurant::factory()->create();
        $admin = User::factory()->restaurantAdmin($restaurant->id)->create();

        $this->actingAs($admin)->get(route('admin.restaurants.show', $restaurant))
            ->assertOk()
            ->assertDontSee(__('Preview status for'));
    }

    public function test_the_day_picker_is_shown_to_dispatcher_and_super_admin(): void
    {
        $restaurant = Restaurant::factory()->create();
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($dispatcher)->get(route('admin.restaurants.show', $restaurant))
            ->assertOk()
            ->assertSee(__('Preview status for'));
    }

    public function test_a_dispatcher_can_save_a_preview_weekday_and_it_persists_on_their_account(): void
    {
        $restaurant = Restaurant::factory()->create();
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($dispatcher)->patch(route('admin.restaurants.status-preview.update'), ['weekday' => 1])
            ->assertRedirect();

        $this->assertSame(1, $dispatcher->fresh()->status_preview_weekday);
    }

    public function test_the_preview_weekday_rejects_an_out_of_range_value(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($dispatcher)->patch(route('admin.restaurants.status-preview.update'), ['weekday' => 7])
            ->assertSessionHasErrors('weekday');
    }

    public function test_a_restaurant_admin_cannot_reach_the_preview_weekday_route(): void
    {
        $restaurant = Restaurant::factory()->create();
        $admin = User::factory()->restaurantAdmin($restaurant->id)->create();

        $this->actingAs($admin)->patch(route('admin.restaurants.status-preview.update'), ['weekday' => 1])
            ->assertForbidden();
    }

    public function test_previewing_a_closed_weekday_shows_closed_even_when_currently_open(): void
    {
        $closedDay = ((int) now()->dayOfWeek + 2) % 7;
        $restaurant = Restaurant::factory()->create([
            'opens_at' => null,
            'closes_at' => null,
            'closed_weekdays' => [$closedDay],
        ]);
        $dispatcher = User::factory()->dispatcher()->create(['status_preview_weekday' => $closedDay]);

        $this->assertTrue($restaurant->isOpenNow());

        $this->actingAs($dispatcher)->get(route('admin.restaurants.show', $restaurant))
            ->assertOk()
            ->assertSee(__('Closed all day on :day.', ['day' => $this->weekdayName($closedDay)]));
    }

    private function weekdayName(int $day): string
    {
        return [__('Sunday'), __('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday')][$day];
    }
}
