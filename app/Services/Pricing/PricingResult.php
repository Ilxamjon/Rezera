<?php

namespace App\Services\Pricing;

final class PricingResult
{
    /**
     * @param  list<PricingSegment>  $segments
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public readonly string $currency,
        public readonly int $subtotal,
        public readonly int $hourlyRateAmount,
        public readonly array $segments,
        public readonly array $snapshot,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'hourly_rate_amount' => $this->hourlyRateAmount,
            'segments' => array_map(static fn (PricingSegment $segment): array => $segment->toArray(), $this->segments),
            'applied_rules' => $this->snapshot['applied_rules'] ?? [],
        ];
    }

    /**
     * @return array{hourly_rate_amount: int, subtotal_amount: int, currency: string, pricing_snapshot: array<string, mixed>}
     */
    public function toBasePricing(): array
    {
        return [
            'hourly_rate_amount' => $this->hourlyRateAmount,
            'subtotal_amount' => $this->subtotal,
            'currency' => $this->currency,
            'pricing_snapshot' => $this->toSnapshot(),
        ];
    }
}
