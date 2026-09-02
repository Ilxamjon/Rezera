<?php

namespace Tests\Support;

use App\Models\BusinessHour;

trait BuildsWorkingHoursSchedule
{
    /**
     * @return list<array{weekday: int, is_closed: bool, is_open_24h: bool, opens_at: ?string, closes_at: ?string}>
     */
    protected function defaultWorkingHoursPayload(): array
    {
        return collect(range(1, 7))->map(static fn (int $weekday): array => [
            'weekday' => $weekday,
            'is_closed' => false,
            'is_open_24h' => false,
            'opens_at' => '09:00',
            'closes_at' => '23:00',
        ])->all();
    }

    /**
     * @return list<array{weekday: int, is_closed: bool, is_open_24h: bool, opens_at: ?string, closes_at: ?string}>
     */
    protected function overnightFridaySchedule(): array
    {
        $schedule = $this->defaultWorkingHoursPayload();

        $schedule[4] = [
            'weekday' => 5,
            'is_closed' => false,
            'is_open_24h' => false,
            'opens_at' => '20:00',
            'closes_at' => '04:00',
        ];

        return $schedule;
    }
}
