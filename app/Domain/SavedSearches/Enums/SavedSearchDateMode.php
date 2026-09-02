<?php

namespace App\Domain\SavedSearches\Enums;

enum SavedSearchDateMode: string
{
    case SpecificDate = 'specific_date';
    case NextAvailable = 'next_available';
    case Recurring = 'recurring';
    case DateRange = 'date_range';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }
}
