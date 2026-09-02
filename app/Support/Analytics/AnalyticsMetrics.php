<?php

namespace App\Support\Analytics;

final class AnalyticsMetrics
{
    public static function rate(int|float $numerator, int|float $denominator, int $precision = 4): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round($numerator / $denominator, $precision);
    }

    public static function percent(int|float $numerator, int|float $denominator, int $precision = 2): ?float
    {
        $rate = self::rate($numerator, $denominator, $precision + 2);

        return $rate === null ? null : round($rate * 100, $precision);
    }

    /**
     * @return array{current: int|float, previous: int|float, change_absolute: int|float, change_percent: float|null}
     */
    public static function compare(int|float $current, int|float $previous): array
    {
        $changeAbsolute = $current - $previous;

        return [
            'current' => $current,
            'previous' => $previous,
            'change_absolute' => $changeAbsolute,
            'change_percent' => $previous != 0
                ? round((($current - $previous) / $previous) * 100, 2)
                : null,
        ];
    }

    public static function average(int $total, int $count, int $precision = 2): ?float
    {
        if ($count <= 0) {
            return null;
        }

        return round($total / $count, $precision);
    }
}
