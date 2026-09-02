<?php

namespace App\Domain\Loyalty\Enums;

enum LoyaltyRedemptionStatus: string
{
    case Reserved = 'reserved';
    case Redeemed = 'redeemed';
    case Used = 'used';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
