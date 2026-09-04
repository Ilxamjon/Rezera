<?php

namespace Tests\Support;

use App\Actions\Businesses\UpdateBusinessWorkingHoursAction;
use App\Models\Business;
use App\Models\BusinessHour;
use Illuminate\Support\Collection;

trait SeedsBusinessHours
{
    /**
     * @param  array<int, array<string, mixed>>|Collection<int, array<string, mixed>>  $schedule
     */
    protected function seedBusinessHours(Business $business, array|Collection $schedule): void
    {
        BusinessHour::query()->where('business_id', $business->id)->delete();

        $normalized = collect($schedule)
            ->map(fn (array $hour): array => $this->normalizeBusinessHourEntry($hour))
            ->values()
            ->all();

        app(UpdateBusinessWorkingHoursAction::class)->execute($business, $normalized);
    }

    /**
     * @param  array<string, mixed>  $hour
     * @return array{weekday: int, is_closed: bool, is_open_24h: bool, opens_at: ?string, closes_at: ?string}
     */
    private function normalizeBusinessHourEntry(array $hour): array
    {
        $isClosed = (bool) ($hour['is_closed'] ?? false);
        $isOpen24h = (bool) ($hour['is_open_24h'] ?? false);

        return [
            'weekday' => (int) $hour['weekday'],
            'is_closed' => $isClosed,
            'is_open_24h' => $isOpen24h,
            'opens_at' => $isClosed || $isOpen24h ? null : ($hour['opens_at'] ?? null),
            'closes_at' => $isClosed || $isOpen24h ? null : ($hour['closes_at'] ?? null),
        ];
    }
}
