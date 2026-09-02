<?php

namespace App\Domain\Promotions\Enums;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
