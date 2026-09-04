<?php

namespace Tests\Support;

use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use App\Models\BookingPolicy;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\User;
use App\Services\Businesses\BusinessOnboardingService;

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
            $isClosed = $weekday === 7;

            BusinessHour::query()->updateOrCreate(
                ['business_id' => $business->id, 'weekday' => $weekday],
                [
                    'is_closed' => $isClosed,
                    'is_open_24h' => false,
                    'opens_at' => $isClosed ? null : '09:00',
                    'closes_at' => $isClosed ? null : '22:00',
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

        return app(BusinessOnboardingService::class)->sync($business->fresh());
    }

    protected function businessOwner(Business $business): User
    {
        return User::query()->findOrFail($business->created_by_user_id);
    }
}
