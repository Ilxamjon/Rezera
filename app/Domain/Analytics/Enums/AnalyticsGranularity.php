<?php

namespace App\Domain\Analytics\Enums;

enum AnalyticsGranularity: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function sqlTrunc(): string
    {
        return match ($this) {
            self::Day => 'day',
            self::Week => 'week',
            self::Month => 'month',
        };
    }
}
