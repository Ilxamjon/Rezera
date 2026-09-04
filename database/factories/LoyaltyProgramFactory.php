<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyProgram>
 */
class LoyaltyProgramFactory extends Factory
{
    protected $model = LoyaltyProgram::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => 'Rezera Rewards',
            'description' => fake()->sentence(),
            'is_enabled' => true,
            'earn_rate_points' => 1,
            'earn_amount' => 1000,
            'flat_points_per_reservation' => null,
            'minimum_qualifying_amount' => null,
            'max_points_per_transaction' => null,
            'points_expiration_days' => null,
            'metadata' => null,
        ];
    }
}
