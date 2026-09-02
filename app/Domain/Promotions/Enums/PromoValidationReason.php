<?php

namespace App\Domain\Promotions\Enums;

enum PromoValidationReason: string
{
    case Valid = 'valid';
    case InvalidCode = 'invalid_code';
    case Inactive = 'inactive';
    case NotStarted = 'not_started';
    case Expired = 'expired';
    case UsageLimitReached = 'usage_limit_reached';
    case UserLimitReached = 'user_limit_reached';
    case MinimumAmountNotReached = 'minimum_amount_not_reached';
    case BusinessNotEligible = 'business_not_eligible';
    case CurrencyNotSupported = 'currency_not_supported';
}
