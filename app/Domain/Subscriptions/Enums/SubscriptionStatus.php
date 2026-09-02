<?php

namespace App\Domain\Subscriptions\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * @return list<self>
     */
    public static function effective(): array
    {
        return [self::Trialing, self::Active, self::PastDue, self::Paused];
    }

    public function isEffective(): bool
    {
        return in_array($this, self::effective(), true);
    }
}
