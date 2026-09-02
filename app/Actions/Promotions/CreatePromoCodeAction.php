<?php

namespace App\Actions\Promotions;

use App\Domain\Promotions\Enums\DiscountType;
use App\Domain\Subscriptions\SubscriptionFeature;
use App\Models\Business;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Subscriptions\BusinessEntitlementService;
use App\Support\Promotions\PromoCodeNormalizer;

final class CreatePromoCodeAction
{
    public function __construct(
        private readonly BusinessEntitlementService $entitlements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(?Business $business, User $actor, array $data): PromoCode
    {
        if ($business !== null) {
            $this->entitlements->check($business, SubscriptionFeature::PROMO_CODES);
        }

        $discountType = DiscountType::from($data['discount_type']);

        return PromoCode::query()->create([
            'business_id' => $business?->id,
            'code' => PromoCodeNormalizer::normalize($data['code']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'discount_type' => $discountType,
            'discount_value' => (int) $data['discount_value'],
            'currency' => $discountType === DiscountType::Fixed
                ? ($data['currency'] ?? config('rezera.default_currency', 'UZS'))
                : null,
            'minimum_amount' => isset($data['minimum_amount']) ? (int) $data['minimum_amount'] : null,
            'maximum_discount' => isset($data['maximum_discount']) ? (int) $data['maximum_discount'] : null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'usage_limit' => isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            'usage_count' => 0,
            'per_user_limit' => isset($data['per_user_limit']) ? (int) $data['per_user_limit'] : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'metadata' => $data['metadata'] ?? null,
            'created_by_user_id' => $actor->id,
        ]);
    }
}
