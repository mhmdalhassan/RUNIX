<?php

namespace App\Enums;

enum FeePayer: string
{
    case CUSTOMER = 'customer';
    case MERCHANT = 'merchant';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Customer',
            self::MERCHANT => 'Merchant',
        };
    }
}
