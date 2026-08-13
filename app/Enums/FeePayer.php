<?php

namespace App\Enums;

enum FeePayer: string
{
    case CUSTOMER = 'customer';
    case MERCHANT = 'merchant';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => __('Customer'),
            self::MERCHANT => __('Merchant'),
        };
    }
}
