<?php

namespace App\Actions\Pricing;

use App\Domain\Pricing\Enums\PricingType;
use App\Domain\Subscriptions\SubscriptionFeature;
use App\Models\Business;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\Businesses\BusinessOnboardingService;
use App\Services\Subscriptions\BusinessEntitlementService;

final class CreatePricingRuleAction
{
    public function __construct(
        private readonly BusinessEntitlementService $entitlements,
        private readonly BusinessOnboardingService $onboarding,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Business $business, User $actor, array $data): PricingRule
    {
        $this->entitlements->check($business, SubscriptionFeature::PRICING_ADVANCED);

        $rule = PricingRule::query()->create([
            'business_id' => $business->id,
            'resource_id' => $data['resource_id'] ?? null,
            'resource_group_id' => $data['resource_category_id'] ?? $data['resource_group_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'pricing_type' => PricingType::from($data['pricing_type']),
            'price' => (int) $data['price'],
            'currency' => $data['currency'] ?? config('rezera.default_currency', 'UZS'),
            'day_of_week' => isset($data['day_of_week']) ? (int) $data['day_of_week'] : null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'specific_date' => $data['specific_date'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'metadata' => $data['metadata'] ?? null,
            'created_by_user_id' => $actor->id,
        ]);

        $this->onboarding->sync($business->fresh());

        return $rule;
    }
}
