<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 — public, unauthenticated order tracking via
 * /track/{order:tracking_token}. Covers both the public page itself and
 * the small dispatcher-facing "Tracking Link" addition to
 * admin/orders/show.blade.php that makes the token reachable in practice.
 */
class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    // --- Basic access -------------------------------------------------

    public function test_valid_token_displays_the_tracking_page(): void
    {
        $order = Order::factory()->create();

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertOk();
        $response->assertSee($order->order_number);
    }

    public function test_invalid_token_returns_404(): void
    {
        $response = $this->get('/track/this-token-does-not-exist');

        $response->assertNotFound();
    }

    public function test_tracking_does_not_require_authentication(): void
    {
        $order = Order::factory()->create();

        // No actingAs() anywhere in this test — a genuinely guest request.
        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertOk();
    }

    // --- Internal ID never exposed (spec §13) --------------------------

    /**
     * Robust by construction rather than by string search: builds the
     * actual URL a customer would use and asserts its final path segment
     * is the token, not the id — never relies on scanning the rendered
     * page for a coincidental digit match.
     */
    public function test_public_tracking_url_uses_the_tracking_token_not_the_internal_id(): void
    {
        $order = Order::factory()->create();

        $url = route('track.show', $order->tracking_token);
        $lastSegment = basename(parse_url($url, PHP_URL_PATH));

        $this->assertSame($order->tracking_token, $lastSegment);
        $this->assertNotSame((string) $order->id, $lastSegment);
    }

    /**
     * The internal numeric id must be structurally incapable of resolving
     * a tracking page — not merely "not shown", but not a working lookup
     * key at all. {order:tracking_token} binds strictly by tracking_token,
     * so the id (a small sequential integer) essentially never collides
     * with a real 40-character random token.
     */
    public function test_visiting_track_with_the_internal_id_instead_of_a_token_returns_404(): void
    {
        $order = Order::factory()->create();

        $response = $this->get('/track/'.$order->id);

        $response->assertNotFound();
    }

    /**
     * Targets an explicit id-carrying field/attribute rather than the bare
     * number (spec §13: a bare assertDontSee((string) $order->id) is too
     * weak — the id could coincidentally match part of a date, price, or
     * token substring). The public view never links back into /admin/*,
     * never has a data-order-id/order_id-style attribute, and never
     * renders the tracking token itself (see next test) — those are the
     * only places an id-like value could leak from.
     */
    public function test_tracking_page_does_not_render_an_explicit_order_id_field(): void
    {
        $order = Order::factory()->create();

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertDontSee('data-order-id', false);
        $response->assertDontSee('order_id', false);
        $response->assertDontSee('order-id', false);
        $response->assertDontSee('/admin/orders/', false);
    }

    /**
     * spec §4: the tracking_token itself shouldn't be rendered back into
     * the page "unless absolutely necessary" — it isn't necessary here
     * (the URL the customer is already on is the only thing that needs
     * it), so it's never echoed into the body.
     */
    public function test_tracking_token_is_not_rendered_in_the_page_body(): void
    {
        $order = Order::factory()->create();

        $response = $this->get(route('track.show', $order->tracking_token));

        // The token appears once, in the URL Laravel used to route this
        // request — never a second time inside the rendered HTML.
        $response->assertDontSee($order->tracking_token, false);
    }

    // --- Cross-order isolation ------------------------------------------

    public function test_tracking_token_cannot_reveal_another_order(): void
    {
        $orderA = Order::factory()->create(['pickup_address' => 'Order A pickup, unique street 111']);
        $orderB = Order::factory()->create(['pickup_address' => 'Order B pickup, unique street 222']);

        $response = $this->get(route('track.show', $orderA->tracking_token));

        $response->assertOk();
        $response->assertSee($orderA->order_number);
        $response->assertSee('Order A pickup, unique street 111');
        $response->assertDontSee($orderB->order_number);
        $response->assertDontSee('Order B pickup, unique street 222');
    }

    // --- Customer-safe fields are shown -----------------------------------

    public function test_customer_safe_fields_are_displayed(): void
    {
        $order = Order::factory()->create([
            'pickup_address' => '123 Pickup Street, Beirut',
            'delivery_address' => '456 Delivery Avenue, Beirut',
        ]);

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertSee($order->order_number);
        $response->assertSee('123 Pickup Street, Beirut');
        $response->assertSee('456 Delivery Avenue, Beirut');
        $response->assertSee($order->status->label());
    }

    // --- Internal/private fields are never shown --------------------------

    public function test_internal_and_private_fields_are_not_displayed(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Ultra Secret Customer Name',
            'phone' => '+96170999999',
        ]);
        $driverUser = User::factory()->driver()->create(['name' => 'Ultra Secret Driver Name']);
        $driver = Driver::factory()->create([
            'user_id' => $driverUser->id,
            'phone' => '+96171888888',
            'last_latitude' => 33.8938123,
            'last_longitude' => 35.5018456,
            'last_location_at' => now(),
        ]);
        $order = Order::factory()->accepted()->create([
            'customer_id' => $customer->id,
            'customer_name_snapshot' => 'Ultra Secret Customer Name',
            'customer_phone_snapshot' => '+96170999999',
            'driver_id' => $driver->id,
            'delivery_fee' => 543.21,
            'driver_earning' => 321.09,
            'internal_notes' => 'Ultra secret internal dispatcher note',
        ]);

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertOk();
        $response->assertDontSee('Ultra Secret Customer Name');
        $response->assertDontSee('+96170999999');
        // The driver's NAME is the one deliberate exception (shown once a
        // driver is assigned — see the feature's own test below); their
        // phone number is still never exposed, alongside everything else
        // here.
        $response->assertSee('Ultra Secret Driver Name');
        $response->assertDontSee('+96171888888');
        $response->assertDontSee('Ultra secret internal dispatcher note');
        $response->assertDontSee('543.21');
        $response->assertDontSee('321.09');
    }

    public function test_the_drivers_name_is_shown_once_assigned_but_not_before(): void
    {
        $driverUser = User::factory()->driver()->create(['name' => 'Visible Driver Name']);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);

        $unassigned = Order::factory()->available()->create();
        $this->get(route('track.show', $unassigned->tracking_token))
            ->assertDontSee('Visible Driver Name');

        $assigned = Order::factory()->accepted()->create(['driver_id' => $driver->id]);
        $this->get(route('track.show', $assigned->tracking_token))
            ->assertSee('Visible Driver Name');
    }

    public function test_the_drivers_email_is_never_exposed(): void
    {
        $driverUser = User::factory()->driver()->create(['email' => 'ultra-secret-driver@example.com']);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
        $order = Order::factory()->accepted()->create(['driver_id' => $driver->id]);

        $this->get(route('track.show', $order->tracking_token))
            ->assertDontSee('ultra-secret-driver@example.com');
    }

    public function test_driver_gps_coordinates_are_never_exposed(): void
    {
        $driver = Driver::factory()->create([
            'last_latitude' => 33.8938123,
            'last_longitude' => 35.5018456,
            'last_location_at' => now(),
        ]);
        $order = Order::factory()->accepted()->create([
            'driver_id' => $driver->id,
            'pickup_latitude' => 33.8938123,
            'pickup_longitude' => 35.5018456,
        ]);

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertDontSee('33.8938123');
        $response->assertDontSee('35.5018456');
    }

    public function test_status_history_notes_and_staff_identity_are_not_displayed(): void
    {
        $dispatcher = User::factory()->dispatcher()->create(['name' => 'Ultra Secret Dispatcher Name']);
        $order = Order::factory()->available()->create();

        app(OrderTransitionService::class)->transition(
            $order,
            OrderStatus::CANCELLED,
            $dispatcher,
            'Ultra secret cancellation reason for staff only',
        );

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertDontSee('Ultra Secret Dispatcher Name');
        $response->assertDontSee('Ultra secret cancellation reason for staff only');
    }

    // --- Timeline / terminal statuses --------------------------------------

    /**
     * Three transitions on purpose, not two: with only two hops
     * ("Available → Accepted" / "Accepted → Cancelled"), asserting
     * "Accepted" appears before "Cancelled" would trivially pass just from
     * the *second* row's own arrow text without proving anything about row
     * order. With three hops, the oldest transition's "Available" (as a
     * from_status) only ever appears in the last-rendered row, and the
     * newest transition's "Cancelled" only in the first-rendered row — so
     * this genuinely exercises statusHistories' newest-first ordering
     * (matching components/order-timeline.blade.php's existing
     * convention), not just string adjacency within one row.
     */
    public function test_status_timeline_reflects_real_transitions_newest_first(): void
    {
        $order = Order::factory()->available()->create(['driver_id' => Driver::factory()]);
        $dispatcher = User::factory()->dispatcher()->create();
        $service = app(OrderTransitionService::class);

        $service->transition($order, OrderStatus::ACCEPTED, $dispatcher);
        $service->transition($order, OrderStatus::PICKED_UP, $dispatcher);
        $service->transition($order, OrderStatus::CANCELLED, $dispatcher);

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertOk();
        $response->assertSee(OrderStatus::ACCEPTED->label());
        $response->assertSee(OrderStatus::PICKED_UP->label());
        $response->assertSee(OrderStatus::CANCELLED->label());
        // Newest-first: the most recent transition's "to" status
        // (Cancelled) must render before the oldest transition's "from"
        // status (Available), which only ever appears in the earliest row.
        $response->assertSeeInOrder([
            OrderStatus::CANCELLED->label(),
            OrderStatus::AVAILABLE->label(),
        ]);
    }

    public function test_terminal_delivered_order_renders_correctly(): void
    {
        $order = Order::factory()->delivered()->create();

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertOk();
        $response->assertSee(OrderStatus::DELIVERED->label());
    }

    public function test_terminal_failed_order_renders_correctly(): void
    {
        $order = Order::factory()->failed()->create();

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertOk();
        $response->assertSee(OrderStatus::FAILED->label());
    }

    // --- Live Location card (App\Http\Controllers\OrderLocationController) --

    public function test_the_live_location_card_shows_for_a_non_terminal_order(): void
    {
        $order = Order::factory()->accepted()->create();

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertOk();
        $response->assertSee(__('Live Location'));
        $response->assertSee('id="order-tracking-map-root"', false);
    }

    public function test_the_live_location_card_is_absent_once_terminal(): void
    {
        $order = Order::factory()->delivered()->create();

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertOk();
        $response->assertDontSee(__('Live Location'));
        $response->assertDontSee('id="order-tracking-map-root"', false);
    }

    /**
     * The polling URL a data attribute would otherwise carry is
     * literally the token again (/track/{token}/location) — dropped
     * entirely in favor of deriving it client-side from the page's own
     * address (see order-tracking-map.js), specifically so this
     * doesn't regress test_tracking_token_is_not_rendered_in_the_page_body.
     */
    public function test_the_live_location_card_does_not_render_a_polling_url_containing_the_token(): void
    {
        $order = Order::factory()->accepted()->create();

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertOk();
        $response->assertDontSee($order->tracking_token, false);
    }

    // --- Admin "Tracking Link" (spec §C/§14) -------------------------------

    public function test_dispatcher_sees_the_tracking_link_on_the_order_show_page(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->create();

        $response = $this->actingAs($dispatcher)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertSee(route('track.show', $order->tracking_token), false);
    }

    public function test_admin_tracking_link_uses_the_named_route_and_tracking_token(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->create();

        $expectedUrl = route('track.show', $order->tracking_token);
        $lastSegment = basename(parse_url($expectedUrl, PHP_URL_PATH));

        // The route helper itself must resolve by token, not id — proven
        // the same structural way as the public-page test above.
        $this->assertSame($order->tracking_token, $lastSegment);

        $response = $this->actingAs($dispatcher)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertSee($expectedUrl, false);
    }

    public function test_admin_tracking_link_does_not_expose_the_internal_order_id(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->create();

        $url = route('track.show', $order->tracking_token);

        $this->assertStringNotContainsString('/track/'.$order->id, $url);

        $response = $this->actingAs($dispatcher)->get(route('admin.orders.show', $order));
        $response->assertOk();
        $response->assertDontSee('/track/'.$order->id, false);
    }
}
