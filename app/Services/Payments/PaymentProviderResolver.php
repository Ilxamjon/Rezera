<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Exceptions\Payments\PaymentException;
use App\Services\Payments\Gateways\MockPaymentGateway;

final class PaymentProviderResolver
{
    public function resolve(PaymentProvider $provider): PaymentGatewayInterface
    {
        if (! $this->isEnabled($provider)) {
            throw PaymentException::providerDisabled($provider->value);
        }

        if (! $provider->isImplemented()) {
            throw PaymentException::providerNotImplemented($provider->value);
        }

        return match ($provider) {
            PaymentProvider::Mock => app(MockPaymentGateway::class),
            default => throw PaymentException::providerNotImplemented($provider->value),
        };
    }

    public function isEnabled(PaymentProvider $provider): bool
    {
        return (bool) config('payment.providers.'.$provider->value.'.enabled', false);
    }
}
