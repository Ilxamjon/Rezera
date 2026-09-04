<?php

namespace Tests\Support;

use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\SubscriptionLifecycleService;

trait AssignsBusinessSubscription
{
    protected function ensureDefaultBusinessSubscription(Business $business): void
    {
        $exists = BusinessSubscription::query()
            ->where('business_id', $business->id)
            ->effective()
            ->exists();

        if ($exists) {
            return;
        }

        app(SubscriptionLifecycleService::class)->assignDefaultPlan($business);
    }

    protected function assignProSubscription(Business $business): void
    {
        $plan = SubscriptionPlan::query()->where('code', 'pro')->firstOrFail();

        app(SubscriptionLifecycleService::class)->createSubscription(
            business: $business,
            plan: $plan,
            interval: BillingInterval::Monthly,
            status: SubscriptionStatus::Active,
        );
    }
}
