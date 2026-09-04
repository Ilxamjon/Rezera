<?php

namespace Database\Factories;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Businesses\Enums\OnboardingStatus;
use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Exceptions\Subscriptions\SubscriptionException;
use App\Models\BookingPolicy;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessMember;
use App\Models\BusinessSubscription;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        return [
            'category_id' => BusinessCategory::factory(),
            'created_by_user_id' => User::factory(),
            'status' => BusinessStatus::Approved,
            'is_publicly_listed' => true,
            'verification_status' => BusinessVerificationStatus::Verified,
            'onboarding_status' => OnboardingStatus::Completed,
            'onboarding_completed_at' => now(),
            'name' => fake()->company(),
            'description' => fake()->paragraph(),
            'translations' => null,
            'phone' => '+99890'.fake()->unique()->numerify('#######'),
            'email' => fake()->companyEmail(),
            'country_code' => 'UZ',
            'region' => 'Toshkent shahri',
            'city' => 'Tashkent',
            'district' => fake()->streetName(),
            'address_line' => fake()->streetAddress(),
            'latitude' => fake()->latitude(41.2, 41.4),
            'longitude' => fake()->longitude(69.1, 69.4),
            'timezone' => 'Asia/Tashkent',
            'cover_image_url' => null,
            'rejection_reason' => null,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by_user_id' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Business $business): void {
            BookingPolicy::factory()->create([
                'business_id' => $business->id,
            ]);

            BusinessMember::factory()->create([
                'business_id' => $business->id,
                'user_id' => $business->created_by_user_id,
                'member_role' => BusinessMemberRole::Owner,
                'status' => BusinessMemberStatus::Active,
            ]);

            if (! BusinessSubscription::query()->where('business_id', $business->id)->effective()->exists()) {
                try {
                    app(SubscriptionLifecycleService::class)->assignDefaultPlan(
                        $business,
                        User::query()->find($business->created_by_user_id),
                    );
                } catch (SubscriptionException) {
                    // Some tests intentionally skip plan seeding.
                }
            }
        });
    }

    public function withPolicy(array $attributes = []): static
    {
        return $this->afterCreating(function (Business $business) use ($attributes): void {
            BookingPolicy::query()->updateOrCreate(
                ['business_id' => $business->id],
                array_merge([
                    'confirmation_mode' => ConfirmationMode::Instant,
                ], $attributes),
            );
        });
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => BusinessStatus::Draft,
            'verification_status' => BusinessVerificationStatus::Unverified,
            'onboarding_status' => OnboardingStatus::InProgress,
            'onboarding_completed_at' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
        ]);
    }
}
