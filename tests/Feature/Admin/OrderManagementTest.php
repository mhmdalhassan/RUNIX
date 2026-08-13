<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderOfferResult;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'pickup_address' => '123 Pickup Street, Beirut',
            'delivery_address' => '456 Delivery Avenue, Beirut',
            'delivery_fee' => 10,
            'driver_earning' => 6,
        ], $overrides);
    }

    // --- Pages render for the right role ---------------------------------

    public function test_dispatcher_can_view_every_order_page(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->create();

        $this->actingAs($dispatcher)->get(route('admin.orders.index'))->assertOk()->assertSee('Orders');
        $this->actingAs($dispatcher)->get(route('admin.orders.create'))->assertOk();
        $this->actingAs($dispatcher)->get(route('admin.orders.show', $order))->assertOk();
        $this->actingAs($dispatcher)->get(route('admin.orders.edit', $order))->assertOk();
    }

    public function test_super_admin_can_view_every_order_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $order = Order::factory()->create();

        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.orders.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk();
    }

    public function test_driver_cannot_access_order_management(): void
    {
        $driver = User::factory()->driver()->create();
        $order = Order::factory()->create();

        $this->actingAs($driver)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('admin.orders.create'))->assertForbidden();
        $this->actingAs($driver)->get(route('admin.orders.show', $order))->assertForbidden();
        $this->actingAs($driver)->get(route('admin.orders.edit', $order))->assertForbidden();
        $this->actingAs($driver)->post(route('admin.orders.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($driver)->put(route('admin.orders.update', $order), $this->validPayload())->assertForbidden();
        $this->actingAs($driver)->patch(route('admin.orders.assign', $order), ['driver_id' => 1])->assertForbidden();
    }

    public function test_guest_cannot_access_order_management(): void
    {
        $this->get(route('admin.orders.index'))->assertRedirect(route('login'));
    }

    // --- Creation ----------------------------------------------------------

    public function test_super_admin_can_create_an_order(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', ['pickup_address' => '123 Pickup Street, Beirut']);
    }

    public function test_dispatcher_can_create_an_order(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
    }

    /**
     * An order without a driver picked at creation is published to every
     * eligible driver immediately — it never sits waiting for a
     * dispatcher to manually click "Available" (CreateOrderService).
     */
    public function test_created_order_is_available_immediately(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
        ]));

        $order = Order::firstOrFail();
        $this->assertSame(OrderStatus::AVAILABLE, $order->status);
    }

    public function test_creation_writes_the_initial_history_record(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
        ]));

        $order = Order::firstOrFail();
        // Two rows: the PENDING origin (recordInitial) and the immediate
        // PENDING -> AVAILABLE publish (see
        // test_created_order_is_available_immediately). Ordered by id,
        // not created_at — both rows land in the same request/transaction
        // and can share the same second-precision timestamp.
        $this->assertSame(2, $order->statusHistories()->count());

        $initial = $order->statusHistories()->oldest('id')->first();
        $this->assertNull($initial->from_status);
        $this->assertSame(OrderStatus::PENDING, $initial->to_status);
        $this->assertSame($dispatcher->id, $initial->changed_by);

        $published = $order->statusHistories()->latest('id')->first();
        $this->assertSame(OrderStatus::PENDING, $published->from_status);
        $this->assertSame(OrderStatus::AVAILABLE, $published->to_status);
        $this->assertSame($dispatcher->id, $published->changed_by);
    }

    /**
     * The actual point of publishing immediately: every active, online,
     * unoccupied driver gets a PENDING offer the moment the order is
     * created — not just a status flag flip. Goes through the real
     * OrderTransitionService -> OfferOrderService path, same as an
     * explicit "Available" click always has.
     */
    public function test_created_order_offers_itself_to_eligible_drivers_immediately(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();
        $eligibleDriver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $ineligibleDriver = Driver::factory()->create(['is_active' => true, 'is_online' => false]);

        $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
        ]));

        $order = Order::firstOrFail();

        $this->assertTrue($order->offers()->where('driver_id', $eligibleDriver->id)->exists());
        $this->assertFalse($order->offers()->where('driver_id', $ineligibleDriver->id)->exists());
    }

    /**
     * Explicitly picking a driver at creation still bypasses the open
     * offer pool (unchanged AssignDriverService behavior) — the order
     * goes straight to ACCEPTED with that driver. AssignDriverService's
     * own PENDING -> AVAILABLE hop still fires the same OfferOrderService
     * offer round everyone else gets (an unavoidable side effect of
     * reusing the one AVAILABLE transition), but it immediately cancels
     * whatever came out of it — so no PENDING offer is ever left
     * standing once assignment finishes, which is what actually matters.
     */
    public function test_creating_an_order_with_a_chosen_driver_skips_the_offer_pool(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();
        $chosenDriver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);

        $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
            'driver_id' => $chosenDriver->id,
        ]));

        $order = Order::firstOrFail();

        $this->assertSame(OrderStatus::ACCEPTED, $order->status);
        $this->assertSame($chosenDriver->id, $order->driver_id);
        $this->assertSame(0, $order->offers()->where('result', OrderOfferResult::PENDING->value)->count());
    }

    public function test_creation_generates_an_order_number_and_tracking_token(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
        ]));

        $order = Order::firstOrFail();
        $this->assertMatchesRegularExpression('/^RUN-\d{8}-\d{4}$/', $order->order_number);
        $this->assertGreaterThanOrEqual(32, strlen($order->tracking_token));
    }

    public function test_order_numbers_are_unique_and_sequential_within_a_day(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
                'customer_id' => $customer->id,
            ]));
        }

        $numbers = Order::pluck('order_number')->all();
        $this->assertCount(3, array_unique($numbers));
        sort($numbers);
        $this->assertSame(
            ['0001', '0002', '0003'],
            array_map(fn (string $n) => substr($n, -4), $numbers),
        );
    }

    public function test_a_duplicate_order_number_is_impossible_at_the_database_level(): void
    {
        Order::factory()->create(['order_number' => 'RUN-20260101-0001']);

        $this->expectException(QueryException::class);
        Order::factory()->create(['order_number' => 'RUN-20260101-0001']);
    }

    public function test_a_duplicate_tracking_token_is_impossible_at_the_database_level(): void
    {
        Order::factory()->create(['tracking_token' => str_repeat('a', 40)]);

        $this->expectException(QueryException::class);
        Order::factory()->create(['tracking_token' => str_repeat('a', 40)]);
    }

    // --- Customer ------------------------------------------------------

    public function test_order_requires_a_valid_customer(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => 999999,
        ]));

        $response->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_creation_populates_customer_snapshots(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create(['name' => 'Jane Merchant', 'phone' => '+96170111222']);

        $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
        ]));

        $order = Order::firstOrFail();
        $this->assertSame('Jane Merchant', $order->customer_name_snapshot);
        $this->assertSame('+96170111222', $order->customer_phone_snapshot);
    }

    public function test_snapshot_does_not_change_if_the_customer_is_edited_later(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create(['name' => 'Original Name']);

        $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
        ]));

        $customer->update(['name' => 'Renamed Later']);

        $order = Order::firstOrFail();
        $this->assertSame('Original Name', $order->customer_name_snapshot);
    }

    // --- Driver ------------------------------------------------------------

    public function test_an_active_driver_can_be_assigned_at_creation(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();
        // Phase 4 also requires is_online for manual assignment (spec §14).
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);

        $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
        ]));

        $order = Order::firstOrFail();
        $this->assertSame($driver->id, $order->driver_id);
        $this->assertSame(OrderStatus::ACCEPTED, $order->status);
        $this->assertNotNull($order->assigned_at);
        $this->assertSame($order->id, $driver->fresh()->current_order_id);
    }

    public function test_an_offline_driver_cannot_be_assigned(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => false]);

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
        ]));

        $response->assertSessionHasErrors('driver_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_occupied_driver_cannot_be_assigned(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();
        $busyWith = Order::factory()->accepted()->create();
        $driver = Driver::factory()->create([
            'is_active' => true,
            'is_online' => true,
            'current_order_id' => $busyWith->id,
        ]);

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
        ]));

        $response->assertSessionHasErrors('driver_id');
        // $busyWith already exists — this only proves the *new* order
        // wasn't created too, not that the table is empty.
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_an_inactive_driver_cannot_be_assigned(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();
        $driver = Driver::factory()->create(['is_active' => false]);

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
        ]));

        $response->assertSessionHasErrors('driver_id');
        // Creation is fully atomic (§15) — an ineligible driver rolls
        // back the whole order, not just the assignment step.
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_driver_with_an_inactive_user_account_cannot_be_assigned(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $customer = Customer::factory()->create();
        $driverUser = User::factory()->driver()->inactive()->create();
        $driver = Driver::factory()->create(['user_id' => $driverUser->id, 'is_active' => true]);

        $response = $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
        ]));

        $response->assertSessionHasErrors('driver_id');
        $this->assertDatabaseCount('orders', 0);
    }

    // --- Assignment (existing order) ----------------------------------

    public function test_dispatcher_can_assign_a_driver_to_an_existing_order(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->create(); // PENDING, no driver
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);

        $response = $this->actingAs($dispatcher)->patch(route('admin.orders.assign', $order), [
            'driver_id' => $driver->id,
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertSame($driver->id, $order->driver_id);
        $this->assertSame(OrderStatus::ACCEPTED, $order->status);
    }

    public function test_assigning_an_already_assigned_order_is_rejected(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->accepted()->create();
        $otherDriver = Driver::factory()->create(['is_active' => true]);
        $originalDriverId = $order->driver_id;

        $response = $this->actingAs($dispatcher)->patch(route('admin.orders.assign', $order), [
            'driver_id' => $otherDriver->id,
        ]);

        $response->assertSessionHasErrors('driver_id');
        $order->refresh();
        $this->assertSame($originalDriverId, $order->driver_id);
    }

    // --- Status transitions (HTTP layer; the exhaustive matrix itself is
    //     unit-tested in OrderTransitionServiceTest) ----------------------

    public function test_dispatcher_can_transition_an_order_through_the_ui(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->accepted()->create();

        $response = $this->actingAs($dispatcher)->patch(route('admin.orders.transition', $order), [
            'to_status' => OrderStatus::PICKED_UP->value,
        ]);

        $response->assertRedirect();
        $this->assertSame(OrderStatus::PICKED_UP, $order->fresh()->status);
    }

    public function test_an_invalid_transition_is_rejected_with_an_error(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->create(); // PENDING

        $response = $this->actingAs($dispatcher)->patch(route('admin.orders.transition', $order), [
            'to_status' => OrderStatus::DELIVERED->value,
        ]);

        $response->assertSessionHasErrors('to_status');
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_a_terminal_order_cannot_be_transitioned(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->delivered()->create();

        $response = $this->actingAs($dispatcher)->patch(route('admin.orders.transition', $order), [
            'to_status' => OrderStatus::CANCELLED->value,
        ]);

        $response->assertSessionHasErrors('to_status');
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
    }

    public function test_driver_cannot_transition_an_order(): void
    {
        $driver = User::factory()->driver()->create();
        $order = Order::factory()->accepted()->create();

        $this->actingAs($driver)->patch(route('admin.orders.transition', $order), [
            'to_status' => OrderStatus::PICKED_UP->value,
        ])->assertForbidden();
    }
}
