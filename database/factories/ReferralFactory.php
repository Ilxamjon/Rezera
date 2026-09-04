<?php

namespace Database\Factories;

use App\Domain\Referrals\Enums\ReferralStatus;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referral>
 */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        $referrer = User::factory()->create();
        $referred = User::factory()->create();

        return [
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $referred->id,
            'referral_code_id' => ReferralCode::factory()->create(['user_id' => $referrer->id])->id,
            'status' => ReferralStatus::Registered,
            'qualified_at' => null,
            'rewarded_at' => null,
            'metadata' => null,
        ];
    }
}
