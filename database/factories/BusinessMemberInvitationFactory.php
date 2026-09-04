<?php

namespace Database\Factories;

use App\Domain\Businesses\Enums\BusinessMemberInvitationStatus;
use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Models\Business;
use App\Models\BusinessMemberInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessMemberInvitation>
 */
class BusinessMemberInvitationFactory extends Factory
{
    protected $model = BusinessMemberInvitation::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'phone' => '+99890'.fake()->unique()->numerify('#######'),
            'invited_user_id' => User::factory(),
            'invited_by_user_id' => User::factory(),
            'member_role' => BusinessMemberRole::Staff,
            'job_title' => null,
            'status' => BusinessMemberInvitationStatus::Pending,
            'expires_at' => now()->addDays(14),
            'responded_at' => null,
        ];
    }
}
