<?php

namespace App\Domain\Loyalty\Enums;

enum LoyaltyRewardType: string
{
    case Discount = 'discount';
    case FixedDiscount = 'fixed_discount';
    case FreeReservation = 'free_reservation';
    case BonusPoints = 'bonus_points';
    case Custom = 'custom';
}
