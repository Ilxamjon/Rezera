<?php

namespace App\Services\Subscriptions;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Subscriptions\SubscriptionFeature;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\ResourceGroup;
use Carbon\CarbonImmutable;

final class BusinessUsageService
{
    public function __construct(
        private readonly BusinessEntitlementService $entitlements,
    ) {}

    public function getUsage(Business $business, string $metric): int
    {
        return match ($metric) {
            SubscriptionFeature::RESOURCES_MAX => $this->activeResourceCount($business),
            SubscriptionFeature::RESOURCE_CATEGORIES_MAX => $this->activeResourceCategoryCount($business),
            SubscriptionFeature::STAFF_MAX => $this->activeStaffCount($business),
            SubscriptionFeature::RESERVATIONS_MONTHLY_MAX => $this->monthlyReservationCount($business),
            default => 0,
        };
    }

    public function getLimit(Business $business, string $metric): ?int
    {
        return $this->entitlements->limit($business, $metric);
    }

    public function remaining(Business $business, string $metric): ?int
    {
        $limit = $this->getLimit($business, $metric);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->getUsage($business, $metric));
    }

    public function canConsume(Business $business, string $metric, int $amount = 1): bool
    {
        $limit = $this->getLimit($business, $metric);

        if ($limit === null) {
            return true;
        }

        return ($this->getUsage($business, $metric) + $amount) <= $limit;
    }

    public function assertCanConsume(Business $business, string $metric, int $amount = 1): void
    {
        $current = $this->getUsage($business, $metric);
        $this->entitlements->assertWithinLimit($business, $metric, $current, $amount);
    }

    /**
     * @return array<string, array{used: int, limit: int|null, remaining: int|null}>
     */
    public function summary(Business $business): array
    {
        $metrics = [
            'resources' => SubscriptionFeature::RESOURCES_MAX,
            'resource_categories' => SubscriptionFeature::RESOURCE_CATEGORIES_MAX,
            'staff' => SubscriptionFeature::STAFF_MAX,
            'reservations_monthly' => SubscriptionFeature::RESERVATIONS_MONTHLY_MAX,
        ];

        $summary = [];

        foreach ($metrics as $key => $feature) {
            $used = $this->getUsage($business, $feature);
            $limit = $this->getLimit($business, $feature);

            $summary[$key] = [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $limit === null ? null : max(0, $limit - $used),
            ];
        }

        return $summary;
    }

    private function activeResourceCount(Business $business): int
    {
        return Resource::query()
            ->where('business_id', $business->id)
            ->where('status', ResourceStatus::Active)
            ->count();
    }

    private function activeResourceCategoryCount(Business $business): int
    {
        return ResourceGroup::query()
            ->where('business_id', $business->id)
            ->count();
    }

    private function activeStaffCount(Business $business): int
    {
        return BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('status', BusinessMemberStatus::Active)
            ->where('member_role', '!=', BusinessMemberRole::Owner)
            ->count();
    }

    private function monthlyReservationCount(Business $business): int
    {
        [$start, $end] = $this->usagePeriod($business);

        return Reservation::query()
            ->where('business_id', $business->id)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function usagePeriod(Business $business): array
    {
        $subscription = $this->entitlements->effectiveSubscription($business);

        if ($subscription?->current_period_start !== null && $subscription->current_period_end !== null) {
            return [
                CarbonImmutable::parse($subscription->current_period_start),
                CarbonImmutable::parse($subscription->current_period_end),
            ];
        }

        $now = CarbonImmutable::now('UTC');

        return [$now->startOfMonth(), $now->endOfMonth()];
    }
}
