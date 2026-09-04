<?php

namespace Database\Factories;

use App\Domain\Businesses\Enums\VerificationRequestStatus;
use App\Models\Business;
use App\Models\BusinessVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessVerification> */
class BusinessVerificationFactory extends Factory
{
    protected $model = BusinessVerification::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'status' => VerificationRequestStatus::Pending,
            'submitted_by_user_id' => User::factory(),
            'submitted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => VerificationRequestStatus::Approved,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => VerificationRequestStatus::Rejected,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => User::factory(),
            'rejection_reason' => 'Incomplete information.',
        ]);
    }
}
