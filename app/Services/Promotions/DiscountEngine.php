<?php

namespace App\Services\Promotions;

use App\Domain\Promotions\Enums\PromoRedemptionStatus;
use App\Domain\Promotions\Enums\PromoValidationReason;
use App\Models\Business;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\Resource;
use App\Models\User;
use App\Services\Reservations\ReservationPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class DiscountEngine
{
    public function __construct(
        private readonly PromoCodeValidator $validator,
        private readonly ReservationPricingService $pricingService,
    ) {}

    public function preview(
        User $user,
        Business $business,
        Resource $resource,
        CarbonImmutable $startAtUtc,
        CarbonImmutable $endAtUtc,
        string $timezone,
        string $code,
    ): DiscountResult {
        $base = $this->pricingService->calculateBase($business, $resource, $startAtUtc, $endAtUtc, $timezone);
        $promo = $this->validator->findByCode($code);

        if ($promo === null) {
            return DiscountResult::invalid(
                PromoValidationReason::InvalidCode,
                $base['subtotal_amount'],
                $base['currency'],
            );
        }

        return $this->validator->validate(
            $promo,
            $user,
            $business,
            $base['subtotal_amount'],
            $base['currency'],
        );
    }

    public function resolveForReservation(
        User $user,
        Business $business,
        Resource $resource,
        CarbonImmutable $startAtUtc,
        CarbonImmutable $endAtUtc,
        string $timezone,
        ?string $code,
    ): array {
        $pricing = $this->pricingService->calculateBase($business, $resource, $startAtUtc, $endAtUtc, $timezone);

        if ($code === null || trim($code) === '') {
            return $this->pricingService->finalize($pricing, null);
        }

        $promo = $this->validator->findByCode($code);

        if ($promo === null) {
            throw ValidationException::withMessages([
                'promo_code' => [__('promotions.invalid_code')],
            ]);
        }

        $discount = $this->validator->validate(
            $promo,
            $user,
            $business,
            $pricing['subtotal_amount'],
            $pricing['currency'],
        );

        if (! $discount->isValid) {
            throw ValidationException::withMessages([
                'promo_code' => [__('promotions.'.$discount->reason->value)],
            ]);
        }

        return $this->pricingService->finalize($pricing, $discount);
    }

    public function redeem(
        PromoCode $promo,
        User $user,
        string $reservationId,
        int $discountAmount,
        string $currency,
    ): PromoCodeRedemption {
        $locked = PromoCode::query()->whereKey($promo->id)->lockForUpdate()->firstOrFail();

        if ($locked->usage_limit !== null && $locked->usage_count >= $locked->usage_limit) {
            throw ValidationException::withMessages([
                'promo_code' => [__('promotions.usage_limit_reached')],
            ]);
        }

        if ($locked->per_user_limit !== null) {
            $userUsage = PromoCodeRedemption::query()
                ->where('promo_code_id', $locked->id)
                ->where('user_id', $user->id)
                ->where('status', PromoRedemptionStatus::Redeemed->value)
                ->count();

            if ($userUsage >= $locked->per_user_limit) {
                throw ValidationException::withMessages([
                    'promo_code' => [__('promotions.user_limit_reached')],
                ]);
            }
        }

        $locked->increment('usage_count');

        return PromoCodeRedemption::query()->create([
            'promo_code_id' => $locked->id,
            'user_id' => $user->id,
            'reservation_id' => $reservationId,
            'discount_amount' => $discountAmount,
            'currency' => $currency,
            'status' => PromoRedemptionStatus::Redeemed,
            'redeemed_at' => now(),
        ]);
    }
}
