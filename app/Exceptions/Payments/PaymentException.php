<?php

namespace App\Exceptions\Payments;

use RuntimeException;

final class PaymentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function providerDisabled(string $provider): self
    {
        return new self(
            __('payments.provider_disabled', ['provider' => $provider]),
            'payment_provider_disabled',
        );
    }

    public static function providerNotImplemented(string $provider): self
    {
        return new self(
            __('payments.provider_not_implemented', ['provider' => $provider]),
            'payment_provider_not_implemented',
        );
    }

    public static function notAllowed(string $message): self
    {
        return new self($message, 'payment_not_allowed');
    }

    public static function alreadyActive(): self
    {
        return new self(__('payments.already_active'), 'payment_already_active');
    }

    public static function invalidState(string $message): self
    {
        return new self($message, 'payment_invalid_state');
    }

    public static function invalidWebhookSignature(): self
    {
        return new self(__('payments.invalid_webhook_signature'), 'invalid_webhook_signature');
    }

    public static function simulationDisabled(): self
    {
        return new self(__('payments.simulation_disabled'), 'payment_simulation_disabled');
    }

    public static function providerError(string $message): self
    {
        return new self($message, 'payment_provider_error');
    }
}
