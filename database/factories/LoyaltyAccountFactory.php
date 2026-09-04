<?php

namespace Database\Factories;

use App\Domain\Loyalty\Enums\LoyaltyAccountStatus;
use App\Models\Business;
use App\Models\LoyaltyAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyAccount>
 */
class LoyaltyAccountFactory extends Factory
{
    protected $model = LoyaltyAccount::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'balance' => 0,
            'lifetime_earned' => 0,
            'lifetime_redeemed' => 0,
            'status' => LoyaltyAccountStatus::Active,
        ];
    }
}
