<?php

namespace App\Services\Payments;

use App\Domain\Payments\Enums\PaymentStatus;
use Illuminate\Validation\ValidationException;

final class PaymentTransitionService
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending' => ['processing', 'paid', 'failed', 'cancelled'],
        'processing' => ['paid', 'failed', 'cancelled'],
        'paid' => ['refunded', 'partially_refunded'],
        'failed' => [],
        'cancelled' => [],
        'refunded' => [],
        'partially_refunded' => ['refunded'],
    ];

    public function assertCanTransition(PaymentStatus $from, PaymentStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => [__('payments.invalid_status_transition', [
                    'from' => $from->value,
                    'to' => $to->value,
                ])],
            ]);
        }
    }

    public function canTransition(PaymentStatus $from, PaymentStatus $to): bool
    {
        if ($from === $to) {
            return false;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }
}
