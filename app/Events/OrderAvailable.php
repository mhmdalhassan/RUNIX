<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast publicly on `orders.available` the moment an order is
 * published to the shared board (App\Http\Controllers\Driver\
 * AvailableOrdersController) — the "new card just dropped" counterpart to
 * OrderTaken's "a card just left." Same minimal-payload shape as that
 * event and for the same reason: this is only ever a "go refresh" signal
 * (§12), never the client's source of truth for what's on the board.
 */
class OrderAvailable implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $orderId,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('orders.available')];
    }

    public function broadcastAs(): string
    {
        return 'order.available';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId];
    }
}
