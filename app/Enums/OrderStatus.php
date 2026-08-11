<?php

namespace App\Enums;

/**
 * The only status values an order may ever hold. Transitions between them
 * are never decided here — that's App\Services\Orders\OrderTransitionService,
 * the single authoritative place that knows which hops are legal.
 *
 * Values are lowercase snake_case on purpose: they're used directly as
 * <x-status-badge :status="$order->status->value" /> keys in the design
 * system built in the frontend-foundation phase.
 */
enum OrderStatus: string
{
    case PENDING = 'pending';
    case AVAILABLE = 'available';
    case ACCEPTED = 'accepted';
    case PICKED_UP = 'picked_up';
    case ON_THE_WAY = 'on_the_way';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::AVAILABLE => 'Available',
            self::ACCEPTED => 'Accepted',
            self::PICKED_UP => 'Picked Up',
            self::ON_THE_WAY => 'On the Way',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::FAILED => 'Failed',
        };
    }

    /**
     * Terminal statuses can never transition anywhere — see
     * OrderTransitionService, whose transition map simply has no entry
     * for these.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::DELIVERED, self::CANCELLED, self::FAILED => true,
            default => false,
        };
    }

    /**
     * Statuses that count as "still active" for
     * Driver::activeOrderCount() and operational dashboards — everything
     * except the terminal ones.
     *
     * @return array<int, self>
     */
    public static function activeStatuses(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status) => ! $status->isTerminal(),
        ));
    }
}
