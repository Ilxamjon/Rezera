<?php

namespace App\Actions\Resources;

use App\Domain\Resources\Enums\RateUnit;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Subscriptions\SubscriptionFeature;
use App\Models\Business;
use App\Models\Resource;
use App\Services\Businesses\BusinessOnboardingService;
use App\Services\Subscriptions\BusinessUsageService;

class CreateResourceAction
{
    public function __construct(
        private readonly BusinessUsageService $usage,
        private readonly BusinessOnboardingService $onboarding,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Business $business, array $data): Resource
    {
        $this->usage->assertCanConsume($business, SubscriptionFeature::RESOURCES_MAX);

        $resource = Resource::query()->create([
            ...$data,
            'business_id' => $business->id,
            'resource_type' => $data['resource_type'] ?? ResourceType::Other,
            'status' => $data['status'] ?? ResourceStatus::Active,
            'capacity' => $data['capacity'] ?? 1,
            'currency' => $data['currency'] ?? 'UZS',
            'rate_unit' => $data['rate_unit'] ?? RateUnit::Hour,
            'metadata' => $data['metadata'] ?? new \stdClass,
            'sort_order' => $data['sort_order'] ?? 0,
        ])->fresh(['group']);

        $this->onboarding->sync($business->fresh());

        return $resource;
    }
}
