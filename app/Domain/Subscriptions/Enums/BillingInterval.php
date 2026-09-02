<?php

namespace App\Domain\Subscriptions\Enums;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $interval): string => $interval->value, self::cases());
    }
}
