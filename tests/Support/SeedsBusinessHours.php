<?php

namespace Tests\Support;

use App\Actions\Businesses\UpdateBusinessWorkingHoursAction;
use App\Models\Business;
use App\Models\BusinessHour;

trait SeedsBusinessHours
{
    /**
     * @param  list<array{weekday: int, is_closed: bool, is_open_24h: bool, opens_at: ?string, closes_at: ?string}>  $schedule
     */
    protected function seedBusinessHours(Business $business, array $schedule): void
    {
        BusinessHour::query()->where('business_id', $business->id)->delete();

        app(UpdateBusinessWorkingHoursAction::class)->execute($business, $schedule);
    }
}
