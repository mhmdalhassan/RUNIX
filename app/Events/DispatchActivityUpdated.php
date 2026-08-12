<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A lightweight operational signal for the dispatch dashboard's live
 * activity feed — "something happened, go refresh" rather than a full
 * payload the client would have to keep in sync; the dashboard still
 * polls independently regardless (§12).
 */
class DispatchActivityUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $summary,
        public readonly ?int $orderId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.dispatch')];
    }

    public function broadcastAs(): string
    {
        return 'dispatch.activity';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'summary' => $this->summary,
            'order_id' => $this->orderId,
            'at' => now()->toIso8601String(),
        ];
    }
}
