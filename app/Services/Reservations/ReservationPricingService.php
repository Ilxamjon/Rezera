<?php

namespace App\Services\Reservations;

use App\Models\Business;
use App\Models\Resource;
use App\Services\Pricing\PricingContext;
use App\Services\Pricing\PricingEngine;
use App\Services\Promotions\DiscountResult;
use Carbon\CarbonImmutable;

final class ReservationPricingService
{
    public function __construct(
        private readonly PricingEngine $pricingEngine,
    ) {}

    /**
     * @return array{hourly_rate_amount: int, subtotal_amount: int, currency: string, pricing_snapshot: array<string, mixed>}
     */
    public function calculateBase(
        Business $business,
        Resource $resource,
        CarbonImmutable $startAtUtc,
        CarbonImmutable $endAtUtc,
        string $timezone,
    ): array {
        $result = $this->pricingEngine->calculate(new PricingContext(
            business: $business,
            resource: $resource,
            startAtUtc: $startAtUtc,
            endAtUtc: $endAtUtc,
            timezone: $timezone,
        ));

        return $result->toBasePricing();
    }

    /**
     * Backward-compatible helper when only duration-based pricing is needed.
     *
     * @return array{hourly_rate_amount: int, subtotal_amount: int, currency: string, pricing_snapshot: array<string, mixed>}
     */
    public function calculateBaseFromDuration(Resource $resource, int $durationMinutes): array
    {
        $business = $resource->business ?? $resource->business()->firstOrFail();
        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $startAtUtc = CarbonImmutable::now('UTC');
        $endAtUtc = $startAtUtc->addMinutes($durationMinutes);

        return $this->calculateBase($business, $resource, $startAtUtc, $endAtUtc, $timezone);
    }

    /**
     * @param  array{hourly_rate_amount: int, subtotal_amount: int, currency: string, pricing_snapshot?: array<string, mixed>}  $base
     * @return array{
     *     hourly_rate_amount: int,
     *     subtotal_amount: int,
     *     discount_amount: int,
     *     total_amount: int,
     *     currency: string,
     *     pricing_snapshot?: array<string, mixed>,
     *     promo_code_id?: string|null,
     *     promo_code_snapshot?: string|null,
     *     discount_type?: string|null,
     *     discount_value_snapshot?: int|null
     * }
     */
    public function finalize(array $base, ?DiscountResult $discount): array
    {
        $subtotal = $base['subtotal_amount'];
        $discountAmount = $discount?->discountAmount ?? 0;
        $total = max($subtotal - $discountAmount, 0);

        $result = [
            'hourly_rate_amount' => $base['hourly_rate_amount'],
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discountAmount,
            'total_amount' => $total,
            'currency' => $base['currency'],
            'pricing_snapshot' => $base['pricing_snapshot'] ?? [],
            'promo_code_id' => null,
            'promo_code_snapshot' => null,
            'discount_type' => null,
            'discount_value_snapshot' => null,
        ];

        if ($discount?->promoCode !== null) {
            $result['promo_code_id'] = $discount->promoCode->id;
            $result['promo_code_snapshot'] = $discount->promoCode->code;
            $result['discount_type'] = $discount->promoCode->discount_type?->value;
            $result['discount_value_snapshot'] = (int) $discount->promoCode->discount_value;
        }

        if ($discount !== null && $discount->isValid) {
            $result['pricing_snapshot']['promo'] = [
                'code' => $discount->promoCode?->code,
                'discount_amount' => $discountAmount,
            ];
        }

        return $result;
    }
}
