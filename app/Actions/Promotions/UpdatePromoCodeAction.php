<?php

namespace App\Actions\Promotions;

use App\Domain\Promotions\Enums\DiscountType;
use App\Models\PromoCode;
use App\Support\Promotions\PromoCodeNormalizer;

final class UpdatePromoCodeAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(PromoCode $promo, array $data): PromoCode
    {
        $attributes = [];

        if (array_key_exists('code', $data)) {
            $attributes['code'] = PromoCodeNormalizer::normalize($data['code']);
        }

        foreach (['name', 'description', 'starts_at', 'ends_at', 'metadata', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (array_key_exists('discount_type', $data)) {
            $attributes['discount_type'] = DiscountType::from($data['discount_type']);
        }

        if (array_key_exists('discount_value', $data)) {
            $attributes['discount_value'] = (int) $data['discount_value'];
        }

        foreach (['minimum_amount', 'maximum_discount', 'usage_limit', 'per_user_limit'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field] !== null ? (int) $data[$field] : null;
            }
        }

        if (array_key_exists('currency', $data)) {
            $attributes['currency'] = $data['currency'];
        }

        $promo->update($attributes);

        return $promo->fresh();
    }
}
