<?php

namespace App\Services\Availability;

use App\Models\Business;
use App\Models\BusinessHour;
use App\Support\Time\TimeInterval;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class BusinessHoursResolver
{
    /**
     * @return list<TimeInterval>
     */
    public function openIntervalsForDate(Business $business, CarbonImmutable $date): array
    {
        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $hoursByWeekday = $this->hoursIndexedByWeekday($business);
        $intervals = [];

        $previousWeekday = $date->subDay()->dayOfWeekIso;
        $previousHour = $hoursByWeekday->get($previousWeekday);

        if ($previousHour !== null) {
            $intervals = array_merge(
                $intervals,
                $this->spilloverMorningIntervals($previousHour, $date, $timezone),
            );
        }

        $currentHour = $hoursByWeekday->get($date->dayOfWeekIso);

        if ($currentHour === null || $currentHour->is_closed) {
            return $this->mergeIntervals($intervals);
        }

        if ($currentHour->is_open_24h) {
            $intervals[] = TimeInterval::fromLocalTimes($date, '00:00', '24:00', $timezone);

            return $this->mergeIntervals($intervals);
        }

        $opensAt = $this->formatTime($currentHour->opens_at);
        $closesAt = $this->formatTime($currentHour->closes_at);

        if ($opensAt === null || $closesAt === null) {
            return $this->mergeIntervals($intervals);
        }

        if ($this->isOvernight($opensAt, $closesAt)) {
            $intervals[] = TimeInterval::fromLocalTimes($date, $opensAt, '24:00', $timezone);
            $intervals[] = TimeInterval::fromLocalTimes($date->addDay(), '00:00', $closesAt, $timezone);
        } else {
            $intervals[] = TimeInterval::fromLocalTimes($date, $opensAt, $closesAt, $timezone);
        }

        return $this->mergeIntervals($intervals);
    }

    public function isClosedOnDate(Business $business, CarbonImmutable $date): bool
    {
        return $this->openIntervalsForDate($business, $date) === [];
    }

    public function isOpenAt(Business $business, ?CarbonImmutable $moment = null): bool
    {
        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $moment ??= CarbonImmutable::now($timezone);

        if ($moment->timezone->getName() !== $timezone) {
            $moment = $moment->setTimezone($timezone);
        }

        $openIntervals = $this->openIntervalsForDate($business, $moment->startOfDay());

        foreach ($openIntervals as $interval) {
            if ($moment->greaterThanOrEqualTo($interval->start) && $moment->lessThan($interval->end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, BusinessHour>
     */
    private function hoursIndexedByWeekday(Business $business): Collection
    {
        $business->loadMissing('hours');

        return $business->hours->keyBy('weekday');
    }

    /**
     * @return list<TimeInterval>
     */
    private function spilloverMorningIntervals(
        BusinessHour $previousHour,
        CarbonImmutable $date,
        string $timezone,
    ): array {
        if ($previousHour->is_closed || $previousHour->is_open_24h) {
            return [];
        }

        $opensAt = $this->formatTime($previousHour->opens_at);
        $closesAt = $this->formatTime($previousHour->closes_at);

        if ($opensAt === null || $closesAt === null || ! $this->isOvernight($opensAt, $closesAt)) {
            return [];
        }

        return [TimeInterval::fromLocalTimes($date, '00:00', $closesAt, $timezone)];
    }

    /**
     * @param  list<TimeInterval>  $intervals
     * @return list<TimeInterval>
     */
    private function mergeIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        usort($intervals, static fn (TimeInterval $a, TimeInterval $b): int => $a->start <=> $b->start);

        /** @var list<TimeInterval> $merged */
        $merged = [];
        $current = $intervals[0];

        foreach (array_slice($intervals, 1) as $interval) {
            if ($interval->start->lessThanOrEqualTo($current->end)) {
                if ($interval->end->greaterThan($current->end)) {
                    $current = new TimeInterval($current->start, $interval->end);
                }

                continue;
            }

            $merged[] = $current;
            $current = $interval;
        }

        $merged[] = $current;

        return $merged;
    }

    private function isOvernight(string $opensAt, string $closesAt): bool
    {
        return $closesAt < $opensAt;
    }

    private function formatTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return strlen($value) >= 5 ? substr($value, 0, 5) : $value;
        }

        return null;
    }
}
