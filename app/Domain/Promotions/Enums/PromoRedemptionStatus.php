<?php

namespace App\Domain\Promotions\Enums;

enum PromoRedemptionStatus: string
{
    case Reserved = 'reserved';
    case Redeemed = 'redeemed';
    case Cancelled = 'cancelled';

    public function countsTowardUsage(): bool
    {
        return $this === self::Redeemed;
    }
}
