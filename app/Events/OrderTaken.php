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
 * waiting for their next poll. Payload deliberately stays minimal: no
 * customer PII, not even the pickup/delivery address, ever goes on a
 * public channel.
 *
 * Used to also carry the claiming driver's name (for a "Taken by <name>"
 * moment on the shared board before the card was removed) — dropped: a
 * driver's real name is PII too, and it doesn't belong on a channel
 * anyone can subscribe to without authenticating. The board now shows a
 * generic "this order was taken" message instead (see
 * resources/js/runix/driver-available-orders.js); an authorized viewer
 * who genuinely needs to know which driver has an order already gets
 * that from the private `order.{orderId}` channel/the order's own
 * authenticated page, never from here.
 */
class OrderTaken implements ShouldBroadcast
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
        ];
    }
}
