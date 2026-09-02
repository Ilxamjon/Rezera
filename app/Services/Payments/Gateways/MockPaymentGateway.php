<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Data\Payments\PaymentGatewayResult;
use App\Data\Payments\PaymentGatewayStatusResult;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Development/test payment gateway. Not for production use.
 */
final class MockPaymentGateway implements PaymentGatewayInterface
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Mock;
    }

    public function createPayment(Payment $payment): PaymentGatewayResult
    {
        $providerPaymentId = 'mock_'.Str::uuid()->toString();

        return new PaymentGatewayResult(
            success: true,
            provider: PaymentProvider::Mock,
            providerPaymentId: $providerPaymentId,
            status: PaymentStatus::Pending,
            paymentUrl: null,
            amount: $payment->amount,
            currency: $payment->currency,
            metadata: ['simulation' => config('payment.providers.mock.allow_simulation')],
        );
    }

    public function getPaymentStatus(Payment $payment): PaymentGatewayStatusResult
    {
        return new PaymentGatewayStatusResult(
            provider: PaymentProvider::Mock,
            status: $payment->status ?? PaymentStatus::Pending,
            providerPaymentId: $payment->provider_payment_id,
            failureReason: $payment->failure_reason,
            metadata: $payment->metadata ?? [],
        );
    }

    public function cancelPayment(Payment $payment): PaymentGatewayResult
    {
        return new PaymentGatewayResult(
            success: true,
            provider: PaymentProvider::Mock,
            providerPaymentId: $payment->provider_payment_id,
            status: PaymentStatus::Cancelled,
            amount: $payment->amount,
            currency: $payment->currency,
        );
    }

    public function refundPayment(Payment $payment, int $amount, ?string $reason = null): PaymentGatewayResult
    {
        unset($reason);

        $status = $amount < $payment->amount
            ? PaymentStatus::PartiallyRefunded
            : PaymentStatus::Refunded;

        return new PaymentGatewayResult(
            success: true,
            provider: PaymentProvider::Mock,
            providerPaymentId: $payment->provider_payment_id,
            status: $status,
            amount: $amount,
            currency: $payment->currency,
        );
    }

    public function handleCallback(array $payload): PaymentGatewayResult
    {
        $status = PaymentStatus::tryFrom((string) ($payload['status'] ?? '')) ?? PaymentStatus::Paid;

        return new PaymentGatewayResult(
            success: $status !== PaymentStatus::Failed,
            provider: PaymentProvider::Mock,
            providerPaymentId: (string) ($payload['provider_payment_id'] ?? ''),
            status: $status,
            failureReason: $payload['failure_reason'] ?? null,
            metadata: $payload,
        );
    }
}
