<?php

namespace Tests\Support;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Models\BookingPolicy;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\BusinessMember;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\User;

trait CompletesBusinessOnboarding
{
    protected function completeBusinessOnboarding(Business $business): Business
    {
        $business->update([
            'description' => $business->description ?: 'Complete business description for onboarding.',
            'phone' => $business->phone ?: '+998901112233',
            'city' => $business->city ?: 'Tashkent',
            'address_line' => $business->address_line ?: 'Amir Temur 1',
            'latitude' => $business->latitude ?: 41.311081,
            'longitude' => $business->longitude ?: 69.240562,
            'timezone' => $business->timezone ?: 'Asia/Tashkent',
        ]);

        BookingPolicy::query()->updateOrCreate(
            ['business_id' => $business->id],
            ['confirmation_mode' => ConfirmationMode::Instant],
        );

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            BusinessHour::query()->updateOrCreate(
                ['business_id' => $business->id, 'weekday' => $weekday],
                [
                    'is_closed' => $weekday === 7,
                    'is_open_24h' => false,
                    'opens_at' => '09:00',
                    'closes_at' => '22:00',
                ],
            );
        }

        $category = ResourceGroup::factory()->create(['business_id' => $business->id]);
        Resource::factory()->create([
            'business_id' => $business->id,
            'resource_group_id' => $category->id,
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 25000,
            'resource_type' => ResourceType::Pc,
        ]);

        return app(\App\Services\Businesses\BusinessOnboardingService::class)->sync($business->fresh());
    }

    protected function businessOwner(Business $business): User
    {
        return User::query()->findOrFail($business->created_by_user_id);
    }
}
