<?php

namespace App\Actions\Businesses;

use App\Models\Business;
use App\Models\BusinessHour;
use App\Services\Businesses\BusinessOnboardingService;
use Illuminate\Support\Facades\DB;

class UpdateBusinessWorkingHoursAction
{
    public function __construct(
        private readonly BusinessOnboardingService $onboarding,
    ) {}
    /**
     * @param  list<array{weekday: int, is_closed: bool, is_open_24h: bool, opens_at: ?string, closes_at: ?string}>  $hours
     * @return \Illuminate\Support\Collection<int, BusinessHour>
     */
    public function execute(Business $business, array $hours)
    {
        $created = DB::transaction(function () use ($business, $hours) {
            BusinessHour::query()->where('business_id', $business->id)->delete();

            $collection = collect();

            foreach ($hours as $hour) {
                $collection->push(BusinessHour::query()->create([
                    'business_id' => $business->id,
                    'weekday' => $hour['weekday'],
                    'is_closed' => $hour['is_closed'],
                    'is_open_24h' => $hour['is_open_24h'],
                    'opens_at' => $hour['opens_at'],
                    'closes_at' => $hour['closes_at'],
                ]));
            }

            return $collection->sortBy('weekday')->values();
        });

        $this->onboarding->sync($business->fresh());

        return $created;
    }
}
