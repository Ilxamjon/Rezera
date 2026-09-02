<?php

namespace App\Domain\SavedSearches\Enums;

enum SavedSearchAlertFrequency: string
{
    case Instant = 'instant';
    case Daily = 'daily';
    case Weekly = 'weekly';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $frequency): string => $frequency->value, self::cases());
    }
}
