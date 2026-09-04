<?php

namespace Database\Factories;

use App\Domain\Loyalty\Enums\LoyaltyRewardType;
use App\Models\Business;
use App\Models\LoyaltyReward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyReward>
 */
class LoyaltyRewardFactory extends Factory
{
    protected $model = LoyaltyReward::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'points_cost' => 500,
            'reward_type' => LoyaltyRewardType::BonusPoints,
            'value' => 100,
            'currency' => 'UZS',
            'stock' => null,
            'max_redemptions_per_user' => null,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'metadata' => null,
        ];
    }
}
