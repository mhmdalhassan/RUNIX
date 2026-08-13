<?php

namespace App\Enums;

/**
 * Lifecycle of a single App\Models\OrderOffer row. Unlike OrderStatus this
 * has no transition-map service — the lifecycle is simple enough
 * (PENDING -> exactly one of the other four, once) that each writer
 * (App\Services\Orders\OfferOrderService/RespondToOrderOfferService/
 * ExpireOrderOfferJob) sets it directly.
 */
enum OrderOfferResult: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::ACCEPTED => __('Accepted'),
            self::REJECTED => __('Rejected'),
            self::EXPIRED => __('Expired'),
            self::CANCELLED => __('Cancelled'),
        };
    }
}
