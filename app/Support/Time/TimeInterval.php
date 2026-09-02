<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Half-open local-time interval [start, end).
 */
final class TimeInterval
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {
        if ($end->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException('Interval end must be after start.');
        }
    }

    public static function fromLocalTimes(
        CarbonImmutable $date,
        string $startTime,
        string $endTime,
        string $timezone,
    ): self {
        Timezone::assertValid($timezone);

        $start = CarbonImmutable::parse($date->format('Y-m-d').' '.$startTime, $timezone);
        $end = self::resolveEnd($date, $startTime, $endTime, $timezone);

        return new self($start, $end);
    }

    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end) && $other->start->lessThan($this->end);
    }

    public function isFullyCoveredBy(array $openIntervals): bool
    {
        if ($openIntervals === []) {
            return false;
        }

        usort($openIntervals, static fn (self $a, self $b): int => $a->start <=> $b->start);

        $cursor = $this->start;

        foreach ($openIntervals as $interval) {
            if ($interval->end->lessThanOrEqualTo($cursor)) {
                continue;
            }

            if ($interval->start->greaterThan($cursor)) {
                return false;
            }

            if ($interval->end->greaterThanOrEqualTo($this->end)) {
                return true;
            }

            $cursor = $interval->end;
        }

        return false;
    }

    public function toUtcStart(): CarbonImmutable
    {
        return $this->start->utc();
    }

    public function toUtcEnd(): CarbonImmutable
    {
        return $this->end->utc();
    }

    private static function resolveEnd(
        CarbonImmutable $date,
        string $startTime,
        string $endTime,
        string $timezone,
    ): CarbonImmutable {
        $startMinutes = self::minutesFromTime($startTime);
        $endMinutes = self::minutesFromTime($endTime);

        $endDate = $date;

        if ($endTime === '24:00' || $endMinutes <= $startMinutes) {
            $endDate = $date->addDay();
        }

        if ($endTime === '24:00') {
            return CarbonImmutable::parse($endDate->format('Y-m-d').' 00:00:00', $timezone);
        }

        return CarbonImmutable::parse($endDate->format('Y-m-d').' '.$endTime, $timezone);
    }

    private static function minutesFromTime(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return ($hours * 60) + $minutes;
    }
}
