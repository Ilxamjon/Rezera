<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Businesses\Enums\OnboardingStatus;
use App\Models\BookingPolicy;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use Illuminate\Support\Facades\DB;

class CreateBusinessAction
{
    public function __construct(
        private readonly SubscriptionLifecycleService $subscriptionLifecycle,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $creator, array $data): Business
    {
        return DB::transaction(function () use ($creator, $data): Business {
            $business = Business::query()->create([
                ...$data,
                'created_by_user_id' => $creator->id,
                'status' => BusinessStatus::Draft,
                'verification_status' => BusinessVerificationStatus::Unverified,
                'onboarding_status' => OnboardingStatus::InProgress,
            ]);

            BusinessMember::query()->create([
                'business_id' => $business->id,
                'user_id' => $creator->id,
                'member_role' => BusinessMemberRole::Owner,
                'status' => BusinessMemberStatus::Active,
            ]);

            BookingPolicy::query()->create([
                'business_id' => $business->id,
                ...config('reservation_rules.defaults'),
            ]);

            $this->subscriptionLifecycle->assignDefaultPlan($business, $creator);

            return $business->fresh(['category', 'bookingPolicy']);
        });
    }
}
