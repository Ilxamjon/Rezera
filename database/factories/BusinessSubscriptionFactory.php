<?php

namespace Database\Factories;

use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessSubscription> */
class BusinessSubscriptionFactory extends Factory
{
    protected $model = BusinessSubscription::class;

    public function definition(): array
    {
        $start = now();

        return [
            'business_id' => Business::factory(),
            'plan_id' => SubscriptionPlan::factory(),
            'status' => SubscriptionStatus::Active,
            'billing_interval' => BillingInterval::Monthly,
            'started_at' => $start,
            'current_period_start' => $start,
            'current_period_end' => $start->copy()->addMonth(),
        ];
    }
}
