<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public, unauthenticated /restaurants pages — every Restaurant an
 * admin/dispatcher creates (Admin\RestaurantController) shows up here
 * automatically once is_active, with no login required. See
 * App\Http\Controllers\RestaurantController.
 */
class RestaurantsPublicPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_listing_shows_active_restaurants(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Sunny Diner', 'is_active' => true]);

        $this->get(route('restaurants.index'))
            ->assertOk()
            ->assertSee('Sunny Diner');
    }

    public function test_the_listing_hides_inactive_restaurants(): void
    {
        Restaurant::factory()->create(['name' => 'Closed Kitchen', 'is_active' => false]);

        $this->get(route('restaurants.index'))
            ->assertOk()
            ->assertDontSee('Closed Kitchen');
    }

    public function test_search_matches_restaurant_name(): void
    {
        Restaurant::factory()->create(['name' => 'Burger Barn', 'is_active' => true]);
        Restaurant::factory()->create(['name' => 'Sushi Spot', 'is_active' => true]);

        $this->get(route('restaurants.index', ['search' => 'Burger']))
            ->assertOk()
            ->assertSee('Burger Barn')
            ->assertDontSee('Sushi Spot');
    }

    public function test_search_matches_an_available_menu_item_name(): void
    {
        $match = Restaurant::factory()->create(['name' => 'Sushi Spot', 'is_active' => true]);
        $other = Restaurant::factory()->create(['name' => 'Burger Barn', 'is_active' => true]);

        $category = MenuCategory::factory()->for($match)->create();
        MenuItem::factory()->for($category)->create(['name' => 'Dragon Roll', 'is_available' => true]);

        $this->get(route('restaurants.index', ['search' => 'Dragon Roll']))
            ->assertOk()
            ->assertSee('Sushi Spot')
            ->assertDontSee('Burger Barn');
    }

    public function test_search_does_not_match_an_unavailable_menu_item(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Sushi Spot', 'is_active' => true]);
        $category = MenuCategory::factory()->for($restaurant)->create();
        MenuItem::factory()->for($category)->create(['name' => 'Dragon Roll', 'is_available' => false]);

        $this->get(route('restaurants.index', ['search' => 'Dragon Roll']))
            ->assertOk()
            ->assertDontSee('Sushi Spot');
    }

    public function test_search_matches_a_menu_category_name(): void
    {
        $match = Restaurant::factory()->create(['name' => 'Sushi Spot', 'is_active' => true]);
        $other = Restaurant::factory()->create(['name' => 'Burger Barn', 'is_active' => true]);
        MenuCategory::factory()->for($match)->create(['name' => 'Cold Drinks']);

        $this->get(route('restaurants.index', ['search' => 'Cold Drinks']))
            ->assertOk()
            ->assertSee('Sushi Spot')
            ->assertDontSee('Burger Barn');
    }

    public function test_the_show_page_lists_available_menu_items_grouped_by_category(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Sushi Spot', 'is_active' => true]);
        $category = MenuCategory::factory()->for($restaurant)->create(['name' => 'Rolls']);
        MenuItem::factory()->for($category)->create(['name' => 'Dragon Roll', 'is_available' => true]);

        $this->get(route('restaurants.show', $restaurant))
            ->assertOk()
            ->assertSee('Sushi Spot')
            ->assertSee('Rolls')
            ->assertSee('Dragon Roll');
    }

    public function test_the_show_page_shows_an_empty_state_when_there_is_no_menu(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);

        $this->get(route('restaurants.show', $restaurant))
            ->assertOk()
            ->assertSee(__('No menu yet'));
    }

    public function test_an_inactive_restaurants_page_404s(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => false]);

        $this->get(route('restaurants.show', $restaurant))->assertNotFound();
    }

    public function test_the_show_page_offers_category_tabs_when_there_are_multiple_categories(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $mains = MenuCategory::factory()->for($restaurant)->create(['name' => 'Mains']);
        $drinks = MenuCategory::factory()->for($restaurant)->create(['name' => 'Drinks']);
        MenuItem::factory()->for($mains)->create(['is_available' => true]);
        MenuItem::factory()->for($drinks)->create(['is_available' => true]);

        // "All" itself isn't checked directly — it's also a substring of
        // the ever-present "All restaurants" back link, so it wouldn't
        // distinguish a rendered tab from a false positive. The
        // aria-label on the tab strip container is unique to it.
        $this->get(route('restaurants.show', $restaurant))
            ->assertOk()
            ->assertSee(__('Filter by category'))
            ->assertSee('Mains')
            ->assertSee('Drinks');
    }

    public function test_the_show_page_hides_category_tabs_when_there_is_only_one_category(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->for($restaurant)->create(['name' => 'Mains']);
        MenuItem::factory()->for($category)->create(['is_available' => true]);

        // The category tabs only earn their keep once there's something
        // to filter down FROM — a single-category menu has nothing for
        // them to do, so the whole tab strip shouldn't render. (Checking
        // for its aria-label rather than the "All" tab's own label,
        // since "All" is also a substring of the "All restaurants" back
        // link that's always present.)
        $this->get(route('restaurants.show', $restaurant))
            ->assertOk()
            ->assertDontSee(__('Filter by category'));
    }

    public function test_the_show_page_includes_a_search_box_and_a_no_results_message_for_client_side_filtering(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->for($restaurant)->create();
        MenuItem::factory()->for($category)->create(['is_available' => true]);

        // The "no results" empty state is rendered into the DOM (hidden
        // via Alpine's x-show, not omitted server-side) so client-side
        // filtering can reveal it without a round-trip — assert it's
        // present rather than trying to execute Alpine in a unit test.
        $this->get(route('restaurants.show', $restaurant))
            ->assertOk()
            ->assertSee(__('Search this menu…'))
            ->assertSee(__('No items match your search'));
    }
}
