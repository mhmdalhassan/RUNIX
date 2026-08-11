<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderTransitionService::class);
    }

    /**
     * The complete truth table from spec §7, checked exhaustively (every
     * ordered pair of the 8 statuses — valid AND invalid alike) rather
     * than one test per hop. Terminal statuses (DELIVERED/CANCELLED/
     * FAILED) fall out of this automatically: every row for them is
     * false.
     */
    public function test_transition_matrix_matches_specification(): void
    {
        $expected = [
            'pending' => ['available', 'cancelled'],
            'available' => ['accepted', 'cancelled'],
            'accepted' => ['picked_up', 'cancelled', 'failed'],
            'picked_up' => ['on_the_way', 'cancelled', 'failed'],
            'on_the_way' => ['delivered', 'failed', 'cancelled'],
            'delivered' => [],
            'cancelled' => [],
            'failed' => [],
        ];

        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                if ($from === $to) {
                    continue;
                }

                $shouldAllow = in_array($to->value, $expected[$from->value], true);

                $this->assertSame(
                    $shouldAllow,
                    $this->service->canTransition($from, $to),
                    "canTransition({$from->value}, {$to->value}) expected ".($shouldAllow ? 'true' : 'false'),
                );
            }
        }
    }

    public function test_terminal_statuses_have_no_allowed_transitions(): void
    {
        foreach ([OrderStatus::DELIVERED, OrderStatus::CANCELLED, OrderStatus::FAILED] as $terminal) {
            $this->assertSame([], $this->service->allowedTransitions($terminal));
            $this->assertTrue($terminal->isTerminal());
        }
    }

    public function test_valid_transition_updates_status_and_matching_timestamp(): void
    {
        $order = Order::factory()->accepted()->create();
        $actor = User::factory()->dispatcher()->create();

        $updated = $this->service->transition($order, OrderStatus::PICKED_UP, $actor);

        $this->assertSame(OrderStatus::PICKED_UP, $updated->status);
        $this->assertNotNull($updated->picked_up_at);
    }

    public function test_valid_transition_creates_exactly_one_history_record(): void
    {
        $order = Order::factory()->accepted()->create();
        $actor = User::factory()->dispatcher()->create();

        $before = $order->statusHistories()->count();

        $this->service->transition($order, OrderStatus::PICKED_UP, $actor, 'Picked up on time');

        $this->assertSame($before + 1, $order->statusHistories()->count());

        $history = $order->statusHistories()->latest('created_at')->first();
        $this->assertSame(OrderStatus::ACCEPTED, $history->from_status);
        $this->assertSame(OrderStatus::PICKED_UP, $history->to_status);
        $this->assertSame($actor->id, $history->changed_by);
        $this->assertSame('Picked up on time', $history->note);
    }

    public function test_invalid_transition_throws_and_changes_nothing(): void
    {
        $order = Order::factory()->create(); // PENDING
        $actor = User::factory()->dispatcher()->create();

        $historyCountBefore = $order->statusHistories()->count();

        $this->expectException(InvalidOrderTransitionException::class);

        try {
            $this->service->transition($order, OrderStatus::DELIVERED, $actor);
        } finally {
            $order->refresh();
            $this->assertSame(OrderStatus::PENDING, $order->status);
            $this->assertSame($historyCountBefore, $order->statusHistories()->count());
        }
    }

    public function test_cannot_transition_out_of_a_terminal_status(): void
    {
        $order = Order::factory()->delivered()->create();
        $actor = User::factory()->dispatcher()->create();

        $this->expectException(InvalidOrderTransitionException::class);

        $this->service->transition($order, OrderStatus::CANCELLED, $actor);
    }

    public function test_cannot_transition_to_accepted_without_a_driver(): void
    {
        $order = Order::factory()->available()->create();
        $this->assertNull($order->driver_id);
        $actor = User::factory()->dispatcher()->create();

        $this->expectException(InvalidOrderTransitionException::class);

        $this->service->transition($order, OrderStatus::ACCEPTED, $actor);
    }

    public function test_can_transition_to_accepted_once_a_driver_is_set(): void
    {
        $order = Order::factory()->available()->create(['driver_id' => Driver::factory()]);
        $actor = User::factory()->dispatcher()->create();

        $updated = $this->service->transition($order, OrderStatus::ACCEPTED, $actor);

        $this->assertSame(OrderStatus::ACCEPTED, $updated->status);
        $this->assertNotNull($updated->accepted_at);
    }

    public function test_record_initial_writes_the_origin_history_row(): void
    {
        $order = Order::factory()->create(); // PENDING
        $actor = User::factory()->dispatcher()->create();

        $this->assertSame(0, $order->statusHistories()->count());

        $this->service->recordInitial($order, $actor);

        $this->assertSame(1, $order->statusHistories()->count());
        $history = $order->statusHistories()->first();
        $this->assertNull($history->from_status);
        $this->assertSame(OrderStatus::PENDING, $history->to_status);
        $this->assertSame($actor->id, $history->changed_by);
    }

    public function test_history_cannot_be_updated_or_deleted(): void
    {
        $order = Order::factory()->create();
        $this->service->recordInitial($order, null);
        $history = $order->statusHistories()->firstOrFail();

        $this->expectException(\LogicException::class);
        $history->update(['note' => 'tampered']);
    }

    public function test_history_delete_is_blocked(): void
    {
        $order = Order::factory()->create();
        $this->service->recordInitial($order, null);
        $history = $order->statusHistories()->firstOrFail();

        $this->expectException(\LogicException::class);
        $history->delete();
    }

    public function test_history_survives_and_cascades_with_its_order(): void
    {
        $order = Order::factory()->create();
        $this->service->recordInitial($order, null);
        $historyId = $order->statusHistories()->firstOrFail()->id;

        $order->delete();

        $this->assertDatabaseMissing('order_status_histories', ['id' => $historyId]);
    }
}
