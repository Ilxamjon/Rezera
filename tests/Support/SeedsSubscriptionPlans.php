<?php

namespace Tests\Support;

use Database\Seeders\SubscriptionPlanSeeder;

trait SeedsSubscriptionPlans
{
    protected function seedSubscriptionPlans(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);
    }
}
