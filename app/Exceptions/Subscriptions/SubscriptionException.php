<?php

namespace App\Exceptions\Subscriptions;

use RuntimeException;

final class SubscriptionException extends RuntimeException
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

    public static function featureRestricted(string $feature, ?string $requiredPlan = null): self
    {
        return new self(
            __('subscriptions.feature_restricted'),
            'PLAN_FEATURE_RESTRICTED',
            array_filter([
                'feature' => $feature,
                'required_plan' => $requiredPlan,
            ]),
        );
    }

    public static function limitReached(string $feature, int $limit, int $current): self
    {
        return new self(
            __('subscriptions.limit_reached'),
            'PLAN_LIMIT_REACHED',
            [
                'feature' => $feature,
                'limit' => $limit,
                'current' => $current,
            ],
        );
    }

    public static function notActive(): self
    {
        return new self(__('subscriptions.not_active'), 'SUBSCRIPTION_NOT_ACTIVE');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            __('subscriptions.invalid_transition'),
            'INVALID_SUBSCRIPTION_TRANSITION',
            ['from' => $from, 'to' => $to],
        );
    }

    public static function planNotAvailable(string $code): self
    {
        return new self(
            __('subscriptions.plan_not_available'),
            'PLAN_NOT_AVAILABLE',
            ['plan_code' => $code],
        );
    }

    public static function paymentRequired(): self
    {
        return new self(__('subscriptions.payment_required'), 'SUBSCRIPTION_PAYMENT_REQUIRED');
    }

    public static function defaultPlanMissing(string $code): self
    {
        return new self(
            __('subscriptions.default_plan_missing', ['code' => $code]),
            'DEFAULT_PLAN_MISSING',
            ['plan_code' => $code],
        );
    }
}
