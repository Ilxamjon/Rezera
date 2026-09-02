<?php

namespace App\Data\Payments;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;

final class PaymentGatewayResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly bool $success,
        public readonly PaymentProvider $provider,
        public readonly ?string $providerPaymentId = null,
        public readonly ?PaymentStatus $status = null,
        public readonly ?string $paymentUrl = null,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $failureReason = null,
        public readonly array $metadata = [],
    ) {}
}
