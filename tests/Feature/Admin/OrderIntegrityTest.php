<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Orders\OrderNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use RuntimeException;
use Tests\TestCase;

class OrderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => Customer::factory()->create()->id,
            'pickup_address' => '123 Pickup Street',
            'delivery_address' => '456 Delivery Avenue',
            'delivery_fee' => 10,
            'driver_earning' => 6,
        ], $overrides);
    }

    /**
     * Forces a failure after the Order row (and its initial history) have
     * already been written but before the transaction commits — mirrors
     * DriverManagementTest's equivalent rollback test. If CreateOrderService
     * weren't properly transactional, this would leave an orphaned Order
     * with no driver assignment and a dangling history row.
     */
    public function test_creation_rolls_back_completely_if_assignment_fails_afterwards(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $driver = Driver::factory()->create(['is_active' => true]);

        OrderStatusHistory::creating(function () {
            static $calls = 0;
            $calls++;

            // Let the initial PENDING history row through, then blow up
            // on the second write (the AVAILABLE/ACCEPTED hops triggered
            // by assigning a driver at creation).
            if ($calls >= 2) {
                throw new RuntimeException('Simulated failure for test.');
            }
        });

        try {
            $this->actingAs($dispatcher)->post(route('admin.orders.store'), $this->validPayload([
                'driver_id' => $driver->id,
            ]));
        } finally {
            OrderStatusHistory::flushEventListeners();
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_a_driver_cannot_be_assigned_to_a_terminal_order(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->cancelled()->create();
        $driver = Driver::factory()->create(['is_active' => true]);

        $response = $this->actingAs($dispatcher)->patch(route('admin.orders.assign', $order), [
            'driver_id' => $driver->id,
        ]);

        $response->assertSessionHasErrors('driver_id');
        $this->assertNull($order->fresh()->driver_id);
    }

    public function test_assignment_never_produces_an_accepted_order_without_a_driver(): void
    {
        // Belt-and-braces: even if a caller tried to force ACCEPTED
        // through the raw transition endpoint on an unassigned order,
        // OrderTransitionService itself refuses (see its own unit test);
        // this confirms the same guarantee holds through the HTTP layer.
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->available()->create();
        $this->assertNull($order->driver_id);

        $response = $this->actingAs($dispatcher)->patch(route('admin.orders.transition', $order), [
            'to_status' => 'accepted',
        ]);

        $response->assertSessionHasErrors('to_status');
        $order->refresh();
        $this->assertSame(OrderStatus::AVAILABLE, $order->status);
        $this->assertNull($order->driver_id);
    }

    public function test_order_number_sequence_is_scoped_to_the_calendar_day(): void
    {
        $generator = app(OrderNumberGenerator::class);

        Date::setTestNow('2026-03-01 10:00:00');
        $first = $generator->generate();

        Date::setTestNow('2026-03-02 10:00:00');
        $second = $generator->generate();

        Date::setTestNow();

        $this->assertSame('RUN-20260301-0001', $first);
        $this->assertSame('RUN-20260302-0001', $second);
    }
}
