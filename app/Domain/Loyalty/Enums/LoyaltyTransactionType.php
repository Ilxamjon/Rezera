<?php

namespace App\Domain\Loyalty\Enums;

enum LoyaltyTransactionType: string
{
    case Earned = 'earned';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
    case Adjusted = 'adjusted';
    case Reversed = 'reversed';
    case Referral = 'referral';
    case Bonus = 'bonus';
}
