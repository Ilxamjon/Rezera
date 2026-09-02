<?php

namespace App\Support\SavedSearches;

use App\Domain\Discovery\Enums\BusinessDiscoverySort;
use App\Models\SavedSearch;
use App\Services\Discovery\BusinessDiscoveryFilters;
use Carbon\CarbonImmutable;

final class SavedSearchCriteria
{
    public function __construct(
        public readonly SavedSearch $savedSearch,
    ) {}

    public function toDiscoveryFilters(int $perPage = 20): BusinessDiscoveryFilters
    {
        $search = $this->savedSearch;

        return new BusinessDiscoveryFilters(
            search: $search->search_query,
            categoryId: $search->category_id,
            city: $search->city,
            district: $search->district,
            region: $search->region,
            countryCode: $search->country_code,
            latitude: $search->latitude,
            longitude: $search->longitude,
            radiusKm: $search->radius_km,
            resourceCategoryId: $search->resource_category_id,
            resourceType: $search->resource_type,
            minPrice: $search->min_price,
            maxPrice: $search->max_price,
            sort: BusinessDiscoverySort::Newest,
            perPage: $perPage,
            userId: $search->user_id,
        );
    }

    /**
     * @return list<array{date: CarbonImmutable, start_time: string, end_time: string}>
     */
    public function availabilityWindows(): array
    {
        $search = $this->savedSearch;

        if ($search->start_time === null || $search->end_time === null) {
            return [];
        }

        $timezone = $search->business?->timezone
            ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $now = CarbonImmutable::now($timezone)->startOfDay();
        $lookahead = (int) config('rezera.saved_searches.lookahead_days', 14);
        $windows = [];

        return match ($search->date_mode) {
            \App\Domain\SavedSearches\Enums\SavedSearchDateMode::SpecificDate => $this->specificDateWindows($search, $now),
            \App\Domain\SavedSearches\Enums\SavedSearchDateMode::DateRange => $this->dateRangeWindows($search, $now, $lookahead),
            \App\Domain\SavedSearches\Enums\SavedSearchDateMode::Recurring => $this->recurringWindows($search, $now, $lookahead),
            \App\Domain\SavedSearches\Enums\SavedSearchDateMode::NextAvailable => $this->nextAvailableWindows($search, $now, $lookahead),
        };
    }

    /**
     * @return list<array{date: CarbonImmutable, start_time: string, end_time: string}>
     */
    private function specificDateWindows(SavedSearch $search, CarbonImmutable $now): array
    {
        if ($search->specific_date === null) {
            return [];
        }

        $date = CarbonImmutable::parse($search->specific_date->toDateString(), $search->business?->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent'))->startOfDay();

        if ($date->lessThan($now)) {
            return [];
        }

        return [[
            'date' => $date,
            'start_time' => $search->start_time,
            'end_time' => $search->end_time,
        ]];
    }

    /**
     * @return list<array{date: CarbonImmutable, start_time: string, end_time: string}>
     */
    private function dateRangeWindows(SavedSearch $search, CarbonImmutable $now, int $lookahead): array
    {
        if ($search->date_from === null || $search->date_to === null) {
            return [];
        }

        $timezone = $search->business?->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $from = CarbonImmutable::parse($search->date_from->toDateString(), $timezone)->startOfDay();
        $to = CarbonImmutable::parse($search->date_to->toDateString(), $timezone)->startOfDay();
        $limit = $now->addDays($lookahead);
        $cursor = $from->greaterThan($now) ? $from : $now;
        $windows = [];

        while ($cursor->lessThanOrEqualTo($to) && $cursor->lessThanOrEqualTo($limit)) {
            $windows[] = [
                'date' => $cursor,
                'start_time' => $search->start_time,
                'end_time' => $search->end_time,
            ];
            $cursor = $cursor->addDay();
        }

        return $windows;
    }

    /**
     * @return list<array{date: CarbonImmutable, start_time: string, end_time: string}>
     */
    private function recurringWindows(SavedSearch $search, CarbonImmutable $now, int $lookahead): array
    {
        $days = $search->days_of_week ?? [];

        if ($days === []) {
            return [];
        }

        $windows = [];

        for ($offset = 0; $offset <= $lookahead; $offset++) {
            $date = $now->addDays($offset);

            if (! in_array($date->dayOfWeek, $days, true)) {
                continue;
            }

            $windows[] = [
                'date' => $date,
                'start_time' => $search->start_time,
                'end_time' => $search->end_time,
            ];
        }

        return $windows;
    }

    /**
     * @return list<array{date: CarbonImmutable, start_time: string, end_time: string}>
     */
    private function nextAvailableWindows(SavedSearch $search, CarbonImmutable $now, int $lookahead): array
    {
        $windows = [];

        for ($offset = 0; $offset <= $lookahead; $offset++) {
            $windows[] = [
                'date' => $now->addDays($offset),
                'start_time' => $search->start_time,
                'end_time' => $search->end_time,
            ];
        }

        return $windows;
    }
}
