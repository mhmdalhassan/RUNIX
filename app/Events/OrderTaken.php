<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast publicly on `orders.taken` the moment an order is claimed —
 * lets every driver's offer list / shared board drop it instantly without
 * waiting for their next poll. Payload deliberately stays minimal
 * otherwise: no customer PII, not even the pickup/delivery address, ever
 * goes on a public channel.
 *
 * $driverName is the one deliberate exception, added for the shared
 * board (App\Http\Controllers\Driver\AvailableOrdersController): the
 * board shows "Taken by <name>" for a moment before the card is removed,
 * so every other driver sees who won it rather than the card just
 * silently vanishing. Nullable/optional so any future caller that
 * doesn't have (or want to disclose) a name can still fire this event.
 */
class OrderTaken implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly ?string $driverName = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('orders.taken')];
    }

    public function broadcastAs(): string
    {
        return 'order.taken';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'driver_name' => $this->driverName,
        ];
    }
}
