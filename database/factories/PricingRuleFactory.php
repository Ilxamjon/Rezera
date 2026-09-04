<?php

namespace Database\Factories;

use App\Domain\Pricing\Enums\PricingType;
use App\Models\Business;
use App\Models\PricingRule;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingRule>
 */
class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'resource_id' => null,
            'resource_group_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'pricing_type' => PricingType::Hourly,
            'price' => 50_000,
            'currency' => 'UZS',
            'day_of_week' => null,
            'start_time' => null,
            'end_time' => null,
            'specific_date' => null,
            'starts_at' => null,
            'ends_at' => null,
            'priority' => 0,
            'is_active' => true,
            'metadata' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function forResource(Resource $resource): static
    {
        return $this->state(fn (): array => [
            'business_id' => $resource->business_id,
            'resource_id' => $resource->id,
            'resource_group_id' => null,
        ]);
    }

    public function forCategory(ResourceGroup $group): static
    {
        return $this->state(fn (): array => [
            'business_id' => $group->business_id,
            'resource_id' => null,
            'resource_group_id' => $group->id,
        ]);
    }

    public function weekdayWindow(int $day, string $start, string $end, int $price): static
    {
        return $this->state(fn (): array => [
            'pricing_type' => PricingType::Hourly,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'price' => $price,
        ]);
    }
}
