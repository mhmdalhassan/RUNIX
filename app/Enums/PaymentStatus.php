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
            self::PENDING => 'Pending',
            self::COLLECTED => 'Collected',
            self::REMITTED => 'Remitted',
            self::SETTLED => 'Settled',
            self::WAIVED => 'Waived',
            self::FAILED => 'Failed',
        };
    }
}
