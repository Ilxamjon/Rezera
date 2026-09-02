<?php

namespace App\Domain\Referrals\Enums;

enum ReferralStatus: string
{
    case Registered = 'registered';
    case Qualified = 'qualified';
    case Rewarded = 'rewarded';
    case Cancelled = 'cancelled';
}
