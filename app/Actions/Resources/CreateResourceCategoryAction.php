<?php

namespace App\Actions\Resources;

use App\Domain\Subscriptions\SubscriptionFeature;
use App\Models\Business;
use App\Models\ResourceGroup;
use App\Services\Subscriptions\BusinessUsageService;

class CreateResourceCategoryAction
{
    public function __construct(
        private readonly BusinessUsageService $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Business $business, array $data): ResourceGroup
    {
        $this->usage->assertCanConsume($business, SubscriptionFeature::RESOURCE_CATEGORIES_MAX);

        return ResourceGroup::query()->create([
            ...$data,
            'business_id' => $business->id,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }
}
