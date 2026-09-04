<?php

namespace App\Support\Analytics;

use App\Domain\Analytics\Enums\AnalyticsDatePreset;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class AnalyticsDateRange
{
    public const MAX_RANGE_DAYS = 366;

    public function __construct(
        public readonly CarbonImmutable $fromLocal,
        public readonly CarbonImmutable $toLocal,
        public readonly string $timezone,
    ) {}

    public static function fromRequest(Business $business, ?string $preset, ?string $from, ?string $to): self
    {
        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $resolvedPreset = AnalyticsDatePreset::tryFrom((string) $preset) ?? AnalyticsDatePreset::Last30Days;

        if ($resolvedPreset === AnalyticsDatePreset::Custom) {
            if ($from === null || $to === null) {
                throw ValidationException::withMessages([
                    'from' => [__('analytics.custom_range_requires_dates')],
                ]);
            }

            $fromLocal = CarbonImmutable::parse($from, $timezone)->startOfDay();
            $toLocal = CarbonImmutable::parse($to, $timezone)->endOfDay();
        } else {
            [$fromLocal, $toLocal] = self::resolvePreset($resolvedPreset, $timezone);
        }

        if ($fromLocal->greaterThan($toLocal)) {
            throw ValidationException::withMessages([
                'from' => [__('analytics.invalid_date_range')],
            ]);
        }

        if ($fromLocal->diffInDays($toLocal) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'to' => [__('analytics.date_range_too_large', ['days' => self::MAX_RANGE_DAYS])],
            ]);
        }

        return new self($fromLocal, $toLocal, $timezone);
    }

    public static function preset(Business $business, AnalyticsDatePreset $preset): self
    {
        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        [$fromLocal, $toLocal] = self::resolvePreset($preset, $timezone);

        return new self($fromLocal, $toLocal, $timezone);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function resolvePreset(AnalyticsDatePreset $preset, string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);

        return match ($preset) {
            AnalyticsDatePreset::Today => [$now->startOfDay(), $now->endOfDay()],
            AnalyticsDatePreset::Yesterday => [
                $now->subDay()->startOfDay(),
                $now->subDay()->endOfDay(),
            ],
            AnalyticsDatePreset::Last7Days => [
                $now->subDays(6)->startOfDay(),
                $now->endOfDay(),
            ],
            AnalyticsDatePreset::Last30Days => [
                $now->subDays(29)->startOfDay(),
                $now->endOfDay(),
            ],
            AnalyticsDatePreset::ThisMonth => [
                $now->startOfMonth()->startOfDay(),
                $now->endOfMonth()->endOfDay(),
            ],
            AnalyticsDatePreset::LastMonth => (function () use ($now) {
                $lastMonth = $now->subMonthNoOverflow();

                return [
                    $lastMonth->startOfMonth()->startOfDay(),
                    $lastMonth->endOfMonth()->endOfDay(),
                ];
            })(),
            AnalyticsDatePreset::Custom => throw new \InvalidArgumentException('Custom preset requires explicit dates.'),
        };
    }

    public function utcStart(): CarbonImmutable
    {
        return $this->fromLocal->utc();
    }

    public function utcEnd(): CarbonImmutable
    {
        return $this->toLocal->utc();
    }

    public function previousPeriod(): self
    {
        $days = (int) ($this->fromLocal->startOfDay()->diffInDays($this->toLocal->startOfDay()) + 1);
        $previousEnd = $this->fromLocal->subDay()->endOfDay();
        $previousStart = $previousEnd->subDays($days - 1)->startOfDay();

        return new self($previousStart, $previousEnd, $this->timezone);
    }

    public static function fromExplicit(Business $business, string $from, string $to): self
    {
        return self::fromRequest($business, AnalyticsDatePreset::Custom->value, $from, $to);
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
}
