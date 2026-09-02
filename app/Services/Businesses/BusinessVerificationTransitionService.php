<?php

namespace App\Services\Businesses;

use App\Domain\Businesses\Enums\VerificationRequestStatus;
use App\Exceptions\Businesses\BusinessVerificationException;

final class BusinessVerificationTransitionService
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending' => ['approved', 'rejected', 'cancelled'],
        'rejected' => [],
        'approved' => [],
        'cancelled' => [],
    ];

    public function assertCanTransition(VerificationRequestStatus $from, VerificationRequestStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        $allowed = self::TRANSITIONS[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw BusinessVerificationException::invalidTransition($from->value, $to->value);
        }
    }
}
