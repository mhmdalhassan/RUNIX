<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast publicly on `order.{orderId}.location` every time an occupied
 * driver's location is updated (App\Services\Drivers\
 * UpdateDriverLocationService) — lets the public order-tracking page
 * (App\Http\Controllers\OrderLocationController is the actual source of
 * truth it polls; this is just the "go refresh sooner" layer, same
 * relationship OrderAvailable/OrderTaken have to their own boards) show
 * the driver's position live, without the customer ever authenticating.
 *
 * Public, not a PrivateChannel, and deliberately on its own per-order
 * channel rather than reusing `order.{orderId}` (that one's private —
 * staff/the assigned driver only). Payload is lat/lng and nothing else —
 * same "no PII on a public channel" rule OrderTaken/OrderAvailable
 * already follow; a tracking-token holder already sees the order's own
 * addresses on the page itself, so this adds no new disclosure beyond
 * "where is the vehicle."
 */
class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly float $latitude,
        public readonly float $longitude,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('order.'.$this->orderId.'.location')];
    }

    public function broadcastAs(): string
    {
        return 'driver.location-updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
