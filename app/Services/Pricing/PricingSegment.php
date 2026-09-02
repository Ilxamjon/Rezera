<?php

namespace App\Services\Pricing;

final class PricingSegment
{
    public function __construct(
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly int $unitPrice,
        public readonly int $amount,
        public readonly string $pricingType,
        public readonly ?string $ruleName = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'unit_price' => $this->unitPrice,
            'amount' => $this->amount,
            'pricing_type' => $this->pricingType,
            'rule_name' => $this->ruleName,
        ];
    }
}
