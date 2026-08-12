<?php

namespace App\Notifications;

use App\Models\OrderOffer;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * The notification abstraction for order offers (spec §15) — delivered
 * only via broadcast (no database/mail channel; customer notifications and
 * WhatsApp are explicitly out of scope for this phase). Payload
 * deliberately excludes customer name/phone — an offer card only needs
 * pickup/delivery/fee/expiry to decide (spec §8).
 */
class NewOrderOfferNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly OrderOffer $offer,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('driver.'.$this->offer->driver_id)];
    }

    public function broadcastType(): string
    {
        return 'order.offer-created';
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $order = $this->offer->order;

        return new BroadcastMessage([
            'offer_id' => $this->offer->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'pickup_address' => $order->pickup_address,
            'delivery_address' => $order->delivery_address,
            'delivery_fee' => (string) $order->delivery_fee,
            'expires_at' => $this->offer->offered_at->copy()->addMinutes(2)->toIso8601String(),
        ]);
    }
}
