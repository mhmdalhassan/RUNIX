<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COLLECTED = 'collected';
    case REMITTED = 'remitted';
    case SETTLED = 'settled';
    case WAIVED = 'waived';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::COLLECTED => __('Collected'),
            self::REMITTED => __('Remitted'),
            self::SETTLED => __('Settled'),
            self::WAIVED => __('Waived'),
            self::FAILED => __('Failed'),
        };
    }
}
