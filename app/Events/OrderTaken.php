<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast publicly on `orders.taken` the moment an order is claimed —
 * lets every driver's offer list drop it instantly without waiting for
 * their next poll, without exposing anything about the order itself.
 * Payload is intentionally just the id (spec §11): no customer PII, not
 * even the pickup/delivery address, ever goes on a public channel.
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
        return ['order_id' => $this->orderId];
    }
}
