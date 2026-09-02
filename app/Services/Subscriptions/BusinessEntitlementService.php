<?php

namespace App\Services\Subscriptions;

use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Exceptions\Subscriptions\SubscriptionException;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;

final class BusinessEntitlementService
{
    public function __construct(
        private readonly SubscriptionPlanResolver $planResolver,
    ) {}

    public function effectivePlan(Business $business): SubscriptionPlan
    {
        $subscription = $this->effectiveSubscription($business);

        return $subscription?->plan ?? $this->planResolver->defaultPlan();
    }

    public function effectiveSubscription(Business $business): ?BusinessSubscription
    {
        return BusinessSubscription::query()
            ->where('business_id', $business->id)
            ->effective()
            ->with('plan.entitlements')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function entitlements(Business $business): array
    {
        $plan = $this->effectivePlan($business);

        return $this->planResolver->entitlementsForPlan($plan);
    }

    public function has(Business $business, string $feature): bool
    {
        $value = $this->value($business, $feature);

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value > 0;
        }

        return $value !== null && $value !== '' && $value !== 0;
    }

    public function can(Business $business, string $feature): bool
    {
        return $this->has($business, $feature);
    }

    public function value(Business $business, string $feature): mixed
    {
        return $this->entitlements($business)[$feature] ?? null;
    }

    public function limit(Business $business, string $feature): ?int
    {
        $value = $this->value($business, $feature);

        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? null : 0;
        }

        return (int) $value;
    }

    public function check(Business $business, string $feature): void
    {
        if (! $this->has($business, $feature)) {
            throw SubscriptionException::featureRestricted(
                $feature,
                $this->planResolver->minimumPlanCodeForFeature($feature),
            );
        }
    }

    public function assertWithinLimit(Business $business, string $feature, int $currentUsage, int $increment = 1): void
    {
        $limit = $this->limit($business, $feature);

        if ($limit === null) {
            return;
        }

        if (($currentUsage + $increment) > $limit) {
            throw SubscriptionException::limitReached($feature, $limit, $currentUsage);
        }
    }

    public function assertSubscriptionActive(Business $business): void
    {
        $subscription = $this->effectiveSubscription($business);

        if ($subscription === null) {
            return;
        }

        if (! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
            throw SubscriptionException::notActive();
        }
    }
}
