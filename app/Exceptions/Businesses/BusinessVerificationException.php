<?php

namespace App\Exceptions\Businesses;

use RuntimeException;

final class BusinessVerificationException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function onboardingIncomplete(array $missingSteps): self
    {
        return new self(
            __('onboarding.verification_onboarding_incomplete'),
            'ONBOARDING_INCOMPLETE',
            ['missing_steps' => $missingSteps],
        );
    }

    public static function alreadyPending(): self
    {
        return new self(__('onboarding.verification_already_pending'), 'VERIFICATION_ALREADY_PENDING');
    }

    public static function notPending(): self
    {
        return new self(__('onboarding.verification_not_pending'), 'VERIFICATION_NOT_PENDING');
    }

    public static function cannotResubmit(): self
    {
        return new self(__('onboarding.verification_cannot_resubmit'), 'VERIFICATION_CANNOT_RESUBMIT');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            __('onboarding.verification_invalid_transition'),
            'INVALID_VERIFICATION_TRANSITION',
            ['from' => $from, 'to' => $to],
        );
    }
}
