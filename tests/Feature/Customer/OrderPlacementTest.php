<?php

namespace Tests\Feature\Customer;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * A customer placing their own order from a restaurant's public menu —
 * App\Services\Orders\CreateCustomerOrderService, the self-service
 * counterpart to Admin\OrderController's dispatcher-only flow (still
 * covered by OrderManagementTest, untouched). See that service's own
 * docblock for why delivery_fee/driver_earning are flat config defaults
 * here rather than dispatcher input.
 */
class OrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    private function eligibleCustomer(): Customer
    {
        return Customer::factory()->withAccount()->create(['phone' => '+9613123456']);
    }

    private function restaurantWithMenu(): array
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->for($restaurant)->create();
        $burger = MenuItem::factory()->for($category)->create(['name' => 'Cheeseburger', 'price' => 9.50, 'is_available' => true]);
        $fries = MenuItem::factory()->for($category)->create(['name' => 'Fries', 'price' => 3.00, 'is_available' => true]);

        return [$restaurant, $burger, $fries];
    }

    public function test_a_customer_can_place_an_order_from_a_restaurants_menu(): void
    {
        [$restaurant, $burger, $fries] = $this->restaurantWithMenu();
        $customer = $this->eligibleCustomer();

        // Deliberately not actingAs($customer, 'customer') — see this
        // session's established gotcha: its shouldUse('customer') side
        // effect would change unrelated default-guard resolution.
        Auth::guard('customer')->login($customer);

        $response = $this->post(route('customer.orders.store'), [
            'restaurant_id' => $restaurant->id,
            'items' => [
                ['menu_item_id' => $burger->id, 'quantity' => 2],
                ['menu_item_id' => $fries->id, 'quantity' => 1],
            ],
            'delivery_address' => '12 Test Street, Beirut',
            'customer_notes' => 'Ring the bell twice.',
        ]);

        $this->assertDatabaseCount('orders', 1);
        $order = Order::first();

        $response->assertRedirect(route('track.show', $order->tracking_token));

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame($restaurant->id, $order->restaurant_id);
        $this->assertSame(OrderStatus::AVAILABLE, $order->status);
        $this->assertSame('12 Test Street, Beirut', $order->delivery_address);
        $this->assertSame('Ring the bell twice.', $order->customer_notes);
        $this->assertNull($order->driver_earning_set_by);

        // 2 burgers @ 9.50 + 1 fries @ 3.00 = 22.00
        $this->assertEquals(22.00, (float) $order->merchant_amount);
        $this->assertEquals((float) config('runix.customer_ordering.default_delivery_fee'), (float) $order->delivery_fee);
        $this->assertEquals((float) config('runix.customer_ordering.default_driver_earning'), (float) $order->driver_earning);
        $this->assertEquals(
            (float) $order->merchant_amount + (float) $order->delivery_fee,
            (float) $order->cod_amount,
        );

        $this->assertDatabaseCount('order_items', 2);
        $burgerLine = $order->items()->where('menu_item_id', $burger->id)->firstOrFail();
        $this->assertSame(2, $burgerLine->quantity);
        $this->assertEquals(9.50, (float) $burgerLine->price_snapshot);
        $this->assertSame('Cheeseburger', $burgerLine->name_snapshot);
    }

    public function test_the_order_appears_on_the_shared_available_orders_board(): void
    {
        [$restaurant, $burger] = $this->restaurantWithMenu();
        $customer = $this->eligibleCustomer();
        Auth::guard('customer')->login($customer);

        $this->post(route('customer.orders.store'), [
            'restaurant_id' => $restaurant->id,
            'items' => [['menu_item_id' => $burger->id, 'quantity' => 1]],
            'delivery_address' => '12 Test Street, Beirut',
        ]);

        $order = Order::firstOrFail();

        $this->assertTrue(Order::available()->whereKey($order->id)->exists());
    }

    public function test_placing_an_order_with_an_incomplete_profile_redirects_to_complete_it(): void
    {
        [$restaurant, $burger] = $this->restaurantWithMenu();
        $customer = Customer::factory()->withAccount()->create(['phone' => null]);
        Auth::guard('customer')->login($customer);

        $response = $this->post(route('customer.orders.store'), [
            'restaurant_id' => $restaurant->id,
            'items' => [['menu_item_id' => $burger->id, 'quantity' => 1]],
            'delivery_address' => '12 Test Street, Beirut',
        ]);

        $response->assertRedirect(route('customer.complete-profile.edit'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_guest_cannot_place_an_order(): void
    {
        [$restaurant, $burger] = $this->restaurantWithMenu();

        $response = $this->post(route('customer.orders.store'), [
            'restaurant_id' => $restaurant->id,
            'items' => [['menu_item_id' => $burger->id, 'quantity' => 1]],
            'delivery_address' => '12 Test Street, Beirut',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_staff_session_cannot_place_a_customer_order(): void
    {
        [$restaurant, $burger] = $this->restaurantWithMenu();

        $response = $this->actingAs(\App\Models\User::factory()->create())
            ->post(route('customer.orders.store'), [
                'restaurant_id' => $restaurant->id,
                'items' => [['menu_item_id' => $burger->id, 'quantity' => 1]],
                'delivery_address' => '12 Test Street, Beirut',
            ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_item_from_a_different_restaurant_is_rejected(): void
    {
        [$restaurant, $burger] = $this->restaurantWithMenu();
        [$otherRestaurant, $otherItem] = $this->restaurantWithMenu();
        $customer = $this->eligibleCustomer();
        Auth::guard('customer')->login($customer);

        $response = $this->post(route('customer.orders.store'), [
            'restaurant_id' => $restaurant->id,
            'items' => [
                ['menu_item_id' => $burger->id, 'quantity' => 1],
                ['menu_item_id' => $otherItem->id, 'quantity' => 1],
            ],
            'delivery_address' => '12 Test Street, Beirut',
        ]);

        $response->assertSessionHasErrors('items.1.menu_item_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_unavailable_item_cannot_be_ordered_even_if_it_slips_past_client_side_state(): void
    {
        [$restaurant, $burger] = $this->restaurantWithMenu();
        $burger->update(['is_available' => false]);
        $customer = $this->eligibleCustomer();
        Auth::guard('customer')->login($customer);

        $response = $this->post(route('customer.orders.store'), [
            'restaurant_id' => $restaurant->id,
            'items' => [['menu_item_id' => $burger->id, 'quantity' => 1]],
            'delivery_address' => '12 Test Street, Beirut',
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_cart_page_can_be_viewed_by_a_guest(): void
    {
        $this->get(route('cart.show'))->assertOk();
    }
}
