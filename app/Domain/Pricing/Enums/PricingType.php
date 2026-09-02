<?php

namespace App\Domain\Pricing\Enums;

enum PricingType: string
{
    case Hourly = 'hourly';
    case Fixed = 'fixed';
}
