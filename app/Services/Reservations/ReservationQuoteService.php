<?php

namespace App\Services\Reservations;

use App\Models\Business;
use App\Models\Resource;
use App\Models\User;
use App\Services\Promotions\DiscountEngine;
use App\Services\Promotions\PromoCodeValidator;
use Carbon\CarbonImmutable;

final class ReservationQuoteService
{
    public function __construct(
        private readonly ReservationPricingService $pricingService,
        private readonly DiscountEngine $discountEngine,
        private readonly PromoCodeValidator $promoValidator,
    ) {}

    /**
     * @return array{
     *     currency: string,
     *     subtotal: int,
     *     discount: int,
     *     total: int,
     *     segments: list<array<string, mixed>>,
     *     promo?: array<string, mixed>|null
     * }
     */
    public function quote(
        User $user,
        Business $business,
        Resource $resource,
        CarbonImmutable $startAtUtc,
        CarbonImmutable $endAtUtc,
        string $timezone,
        ?string $promoCode = null,
    ): array {
        $base = $this->pricingService->calculateBase($business, $resource, $startAtUtc, $endAtUtc, $timezone);
        $discount = null;

        if ($promoCode !== null && trim($promoCode) !== '') {
            $promo = $this->promoValidator->findByCode($promoCode);

            if ($promo !== null) {
                $discount = $this->promoValidator->validate(
                    $promo,
                    $user,
                    $business,
                    $base['subtotal_amount'],
                    $base['currency'],
                );
            }
        }

        $final = $this->pricingService->finalize($base, $discount?->isValid ? $discount : null);

        $response = [
            'currency' => $final['currency'],
            'subtotal' => $final['subtotal_amount'],
            'discount' => $final['discount_amount'],
            'total' => $final['total_amount'],
            'segments' => $final['pricing_snapshot']['segments'] ?? [],
        ];

        if ($promoCode !== null && trim($promoCode) !== '') {
            $response['promo'] = [
                'code' => strtoupper(trim($promoCode)),
                'valid' => $discount?->isValid ?? false,
                'reason' => $discount?->isValid ? null : ($discount?->reason->value ?? 'invalid_code'),
            ];
        }

        return $response;
    }
}
