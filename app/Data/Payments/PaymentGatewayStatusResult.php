<?php

namespace App\Data\Payments;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;

final class PaymentGatewayStatusResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly PaymentProvider $provider,
        public readonly PaymentStatus $status,
        public readonly ?string $providerPaymentId = null,
        public readonly ?string $failureReason = null,
        public readonly array $metadata = [],
    ) {}
}
