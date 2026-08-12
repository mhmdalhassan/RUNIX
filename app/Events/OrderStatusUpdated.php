<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts a status change to whoever is watching this specific order —
 * the assigned driver and dispatch/admin. Never fired on its own; always
 * alongside a real OrderTransitionService::transition()/
 * ClaimOrderForDriverService::claim() call, so this can never announce a
 * status the database doesn't actually hold.
 */
class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('order.'.$this->order->id)];
    }

    public function broadcastAs(): string
    {
        return 'order.status-updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'status' => $this->order->status->value,
            'status_label' => $this->order->status->label(),
            'driver_id' => $this->order->driver_id,
        ];
    }
}
