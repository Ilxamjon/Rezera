<?php

namespace App\Support\Calendar;

use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class CalendarDateRange
{
    public const MAX_RANGE_DAYS = 31;

    public function __construct(
        public readonly CarbonImmutable $fromLocal,
        public readonly CarbonImmutable $toLocal,
        public readonly string $timezone,
    ) {}

    public static function forDay(Business $business, ?string $date): self
    {
        $timezone = self::timezone($business);
        $day = $date === null || $date === 'today'
            ? CarbonImmutable::now($timezone)->startOfDay()
            : CarbonImmutable::parse($date, $timezone)->startOfDay();

        return new self($day, $day->endOfDay(), $timezone);
    }

    public static function forWeek(Business $business, ?string $date): self
    {
        $timezone = self::timezone($business);
        $anchor = $date === null || $date === 'today'
            ? CarbonImmutable::now($timezone)->startOfDay()
            : CarbonImmutable::parse($date, $timezone)->startOfDay();

        $weekStartsOn = max(1, min(7, (int) config('business_calendar.week_starts_on', 1)));
        $daysFromStart = ($anchor->dayOfWeekIso - $weekStartsOn + 7) % 7;
        $from = $anchor->subDays($daysFromStart)->startOfDay();
        $to = $from->addDays(6)->endOfDay();

        return new self($from, $to, $timezone);
    }

    public static function fromRequest(Business $business, ?string $from, ?string $to, ?int $defaultDays = null): self
    {
        $timezone = self::timezone($business);
        $maxDays = (int) config('business_calendar.max_range_days', self::MAX_RANGE_DAYS);

        if ($from === null && $to === null) {
            $defaultDays ??= (int) config('business_calendar.default_resource_schedule_days', 7);
            $fromLocal = CarbonImmutable::now($timezone)->startOfDay();
            $toLocal = $fromLocal->addDays(max($defaultDays, 1) - 1)->endOfDay();

            return new self($fromLocal, $toLocal, $timezone);
        }

        if ($from === null || $to === null) {
            throw ValidationException::withMessages([
                'from' => [__('calendar.range_requires_dates')],
            ]);
        }

        $fromLocal = CarbonImmutable::parse($from, $timezone)->startOfDay();
        $toLocal = CarbonImmutable::parse($to, $timezone)->endOfDay();

        if ($fromLocal->greaterThan($toLocal)) {
            throw ValidationException::withMessages([
                'from' => [__('calendar.invalid_date_range')],
            ]);
        }

        if ($fromLocal->diffInDays($toLocal) > $maxDays) {
            throw ValidationException::withMessages([
                'to' => [__('calendar.date_range_too_large', ['days' => $maxDays])],
            ]);
        }

        return new self($fromLocal, $toLocal, $timezone);
    }

    public function utcStart(): CarbonImmutable
    {
        return $this->fromLocal->utc();
    }

    public function utcEnd(): CarbonImmutable
    {
        return $this->toLocal->utc();
    }

    /**
     * @return list<CarbonImmutable>
     */
    public function days(): array
    {
        $days = [];
        $cursor = $this->fromLocal->startOfDay();
        $end = $this->toLocal->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor;
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return array{from: string, to: string, timezone: string}
     */
    public function toPeriodArray(): array
    {
        return [
            'from' => $this->fromLocal->toDateString(),
            'to' => $this->toLocal->toDateString(),
            'timezone' => $this->timezone,
        ];
    }

    private static function timezone(Business $business): string
    {
        return $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
    }
}
