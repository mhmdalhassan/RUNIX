<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case ONLINE = 'online';
    case MERCHANT_ACCOUNT = 'merchant_account';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::CARD => 'Card',
            self::ONLINE => 'Online',
            self::MERCHANT_ACCOUNT => 'Merchant Account',
        };
    }
}
