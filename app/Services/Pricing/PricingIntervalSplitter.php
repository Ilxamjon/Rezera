<?php

namespace App\Services\Pricing;

use App\Models\PricingRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class PricingIntervalSplitter
{
    /**
     * @param  Collection<int, PricingRule>  $rules
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function split(
        CarbonImmutable $localStart,
        CarbonImmutable $localEnd,
        string $timezone,
        Collection $rules,
    ): array {
        $breakpoints = [$localStart, $localEnd];

        $dates = $this->datesSpanned($localStart, $localEnd);

        foreach ($dates as $date) {
            foreach ($rules as $rule) {
                if ($rule->start_time === null || $rule->end_time === null) {
                    continue;
                }

                if ($rule->day_of_week !== null && (int) $date->isoWeekday() !== (int) $rule->day_of_week) {
                    continue;
                }

                foreach ($this->timeWindowBoundaries($date, (string) $rule->start_time, (string) $rule->end_time, $timezone) as $point) {
                    if ($point->greaterThan($localStart) && $point->lessThan($localEnd)) {
                        $breakpoints[] = $point;
                    }
                }
            }
        }

        $breakpoints = collect($breakpoints)
            ->unique(fn (CarbonImmutable $point): int => $point->getTimestamp())
            ->sortBy(fn (CarbonImmutable $point): int => $point->getTimestamp())
            ->values();

        $segments = [];

        for ($index = 0; $index < $breakpoints->count() - 1; $index++) {
            /** @var CarbonImmutable $start */
            $start = $breakpoints[$index];
            /** @var CarbonImmutable $end */
            $end = $breakpoints[$index + 1];

            if ($end->greaterThan($start)) {
                $segments[] = [$start, $end];
            }
        }

        return $segments;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function datesSpanned(CarbonImmutable $localStart, CarbonImmutable $localEnd): array
    {
        $dates = [];
        $cursor = $localStart->startOfDay();
        $lastDay = $localEnd->startOfDay();

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $dates[] = $cursor;
            $cursor = $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function timeWindowBoundaries(
        CarbonImmutable $date,
        string $startTime,
        string $endTime,
        string $timezone,
    ): array {
        $start = CarbonImmutable::parse($date->format('Y-m-d').' '.$startTime, $timezone);
        $startMinutes = $this->minutesFromTime($startTime);
        $endMinutes = $this->minutesFromTime($endTime);

        if ($endMinutes <= $startMinutes) {
            $end = CarbonImmutable::parse($date->addDay()->format('Y-m-d').' '.$endTime, $timezone);

            return [$start, $end];
        }

        $end = CarbonImmutable::parse($date->format('Y-m-d').' '.$endTime, $timezone);

        return [$start, $end];
    }

    private function minutesFromTime(string $time): int
    {
        $parts = explode(':', $time);
        $hours = (int) ($parts[0] ?? 0);
        $mins = (int) ($parts[1] ?? 0);

        return ($hours * 60) + $mins;
    }
}
