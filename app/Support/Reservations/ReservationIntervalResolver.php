<?php

namespace App\Support\Reservations;

use App\Support\Time\TimeInterval;
use Carbon\CarbonImmutable;

final class ReservationIntervalResolver
{
    public function resolve(
        CarbonImmutable $date,
        string $startTime,
        string $endTime,
        string $timezone,
    ): TimeInterval {
        return TimeInterval::fromLocalTimes($date, $startTime, $endTime, $timezone);
    }

    public function durationMinutes(TimeInterval $interval): int
    {
        return (int) $interval->start->diffInMinutes($interval->end);
    }

    /**
     * @return array{start_at: CarbonImmutable, end_at: CarbonImmutable, duration_minutes: int}
     */
    public function toUtcPayload(TimeInterval $interval): array
    {
        $startAt = $interval->toUtcStart();
        $endAt = $interval->toUtcEnd();

        return [
            'start_at' => $startAt,
            'end_at' => $endAt,
            'duration_minutes' => (int) $startAt->diffInMinutes($endAt),
        ];
    }

    public function localDate(TimeInterval $interval, string $timezone): string
    {
        return $interval->start->timezone($timezone)->format('Y-m-d');
    }

    public function localStartTime(TimeInterval $interval, string $timezone): string
    {
        return $interval->start->timezone($timezone)->format('H:i');
    }

    public function localEndTime(TimeInterval $interval, string $timezone): string
    {
        return $interval->end->timezone($timezone)->format('H:i');
    }
}
