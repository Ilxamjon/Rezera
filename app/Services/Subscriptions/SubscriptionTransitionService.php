<?php

namespace App\Services\Subscriptions;

use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Exceptions\Subscriptions\SubscriptionException;

final class SubscriptionTransitionService
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'trialing' => ['active', 'cancelled', 'expired', 'past_due'],
        'active' => ['past_due', 'cancelled', 'expired', 'paused'],
        'past_due' => ['active', 'cancelled', 'expired'],
        'paused' => ['active', 'cancelled', 'expired'],
        'cancelled' => ['expired'],
        'expired' => [],
    ];

    public function assertCanTransition(SubscriptionStatus $from, SubscriptionStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        $allowed = self::TRANSITIONS[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw SubscriptionException::invalidTransition($from->value, $to->value);
        }
    }

    public function canTransition(SubscriptionStatus $from, SubscriptionStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }
}
