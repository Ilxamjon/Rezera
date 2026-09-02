<?php

namespace App\Services\Promotions;

use App\Domain\Promotions\Enums\DiscountType;
use App\Domain\Promotions\Enums\PromoRedemptionStatus;
use App\Domain\Promotions\Enums\PromoValidationReason;
use App\Models\Business;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\User;
use App\Support\Promotions\PromoCodeNormalizer;
use Carbon\CarbonImmutable;

final class PromoCodeValidator
{
    public function findByCode(string $code): ?PromoCode
    {
        $normalized = PromoCodeNormalizer::normalize($code);

        return PromoCode::query()
            ->where('code', $normalized)
            ->whereNull('deleted_at')
            ->first();
    }

    public function validate(
        PromoCode $promo,
        User $user,
        Business $business,
        int $subtotal,
        string $currency,
    ): DiscountResult {
        if (! $promo->is_active) {
            return DiscountResult::invalid(PromoValidationReason::Inactive, $subtotal, $currency);
        }

        if ($promo->business_id !== null && $promo->business_id !== $business->id) {
            return DiscountResult::invalid(PromoValidationReason::BusinessNotEligible, $subtotal, $currency);
        }

        $now = CarbonImmutable::now('UTC');

        if ($promo->starts_at !== null && $now->lessThan($promo->starts_at)) {
            return DiscountResult::invalid(PromoValidationReason::NotStarted, $subtotal, $currency);
        }

        if ($promo->ends_at !== null && $now->greaterThan($promo->ends_at)) {
            return DiscountResult::invalid(PromoValidationReason::Expired, $subtotal, $currency);
        }

        if ($promo->minimum_amount !== null && $subtotal < $promo->minimum_amount) {
            return DiscountResult::invalid(PromoValidationReason::MinimumAmountNotReached, $subtotal, $currency);
        }

        if ($promo->discount_type === DiscountType::Fixed) {
            $promoCurrency = $promo->currency ?? config('rezera.default_currency', 'UZS');
            if ($promoCurrency !== $currency) {
                return DiscountResult::invalid(PromoValidationReason::CurrencyNotSupported, $subtotal, $currency);
            }
        }

        if ($promo->usage_limit !== null && $promo->usage_count >= $promo->usage_limit) {
            return DiscountResult::invalid(PromoValidationReason::UsageLimitReached, $subtotal, $currency);
        }

        if ($promo->per_user_limit !== null) {
            $userUsage = PromoCodeRedemption::query()
                ->where('promo_code_id', $promo->id)
                ->where('user_id', $user->id)
                ->where('status', PromoRedemptionStatus::Redeemed->value)
                ->count();

            if ($userUsage >= $promo->per_user_limit) {
                return DiscountResult::invalid(PromoValidationReason::UserLimitReached, $subtotal, $currency);
            }
        }

        $discountAmount = $this->calculateDiscountAmount($promo, $subtotal);
        $total = max($subtotal - $discountAmount, 0);

        return new DiscountResult(
            isValid: true,
            reason: PromoValidationReason::Valid,
            discountAmount: $discountAmount,
            subtotal: $subtotal,
            total: $total,
            currency: $currency,
            promoCode: $promo,
        );
    }

    public function calculateDiscountAmount(PromoCode $promo, int $subtotal): int
    {
        $discount = match ($promo->discount_type) {
            DiscountType::Percentage => (int) round($subtotal * ((int) $promo->discount_value) / 100),
            DiscountType::Fixed => (int) $promo->discount_value,
        };

        if ($promo->discount_type === DiscountType::Percentage && $promo->maximum_discount !== null) {
            $discount = min($discount, (int) $promo->maximum_discount);
        }

        return min($discount, $subtotal);
    }
}
