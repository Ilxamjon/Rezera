<?php

namespace App\Services\Promotions;

use App\Domain\Promotions\Enums\DiscountType;
use App\Domain\Promotions\Enums\PromoValidationReason;
use App\Models\PromoCode;

final class DiscountResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly PromoValidationReason $reason,
        public readonly int $discountAmount = 0,
        public readonly int $subtotal = 0,
        public readonly int $total = 0,
        public readonly string $currency = 'UZS',
        public readonly ?PromoCode $promoCode = null,
    ) {}

    public static function invalid(PromoValidationReason $reason, int $subtotal = 0, string $currency = 'UZS'): self
    {
        return new self(
            isValid: false,
            reason: $reason,
            subtotal: $subtotal,
            total: $subtotal,
            currency: $currency,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        if ($this->promoCode === null) {
            return [];
        }

        return [
            'promo_code_id' => $this->promoCode->id,
            'promo_code_snapshot' => $this->promoCode->code,
            'discount_type' => $this->promoCode->discount_type?->value,
            'discount_value' => (int) $this->promoCode->discount_value,
            'discount_amount' => $this->discountAmount,
        ];
    }
}
