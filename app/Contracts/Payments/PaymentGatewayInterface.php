<?php

namespace App\Contracts\Payments;

use App\Data\Payments\PaymentGatewayResult;
use App\Data\Payments\PaymentGatewayStatusResult;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function provider(): PaymentProvider;

    public function createPayment(Payment $payment): PaymentGatewayResult;

    public function getPaymentStatus(Payment $payment): PaymentGatewayStatusResult;

    public function cancelPayment(Payment $payment): PaymentGatewayResult;

    public function refundPayment(Payment $payment, int $amount, ?string $reason = null): PaymentGatewayResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): PaymentGatewayResult;
}
