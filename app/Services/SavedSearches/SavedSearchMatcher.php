<?php

namespace App\Services\SavedSearches;

use App\Domain\SavedSearches\Enums\SavedSearchAlertFrequency;
use App\Models\Business;
use App\Models\SavedSearch;
use App\Services\Availability\AvailabilityEngine;
use App\Services\Availability\AvailabilityQuery;
use App\Services\Discovery\BusinessDiscoveryQueryBuilder;
use App\Support\SavedSearches\SavedSearchCriteria;
use App\Support\SavedSearches\SavedSearchMatch;
use Illuminate\Database\Eloquent\Builder;

final class SavedSearchMatcher
{
    public function __construct(
        private readonly BusinessDiscoveryQueryBuilder $discoveryQueryBuilder,
        private readonly AvailabilityEngine $availabilityEngine,
    ) {}

    /**
     * @return list<SavedSearchMatch>
     */
    public function findMatches(SavedSearch $savedSearch): array
    {
        if (! $savedSearch->availability_required) {
            return [];
        }

        $criteria = new SavedSearchCriteria($savedSearch);
        $windows = $criteria->availabilityWindows();

        if ($windows === []) {
            return [];
        }

        $businesses = $this->resolveCandidateBusinesses($savedSearch, $criteria);
        $matches = [];

        foreach ($windows as $window) {
            foreach ($businesses as $business) {
                $result = $this->availabilityEngine->calculate(new AvailabilityQuery(
                    business: $business,
                    date: $window['date'],
                    startTime: $window['start_time'],
                    endTime: $window['end_time'],
                    resourceCategoryId: $savedSearch->resource_category_id,
                    resourceId: $savedSearch->resource_id,
                ));

                foreach ($result->resources as $resourceResult) {
                    if (! $resourceResult->isAvailable()) {
                        continue;
                    }

                    $interval = $result->requestedInterval;
                    $matches[] = new SavedSearchMatch(
                        businessId: $business->id,
                        businessName: $business->name,
                        resourceId: $resourceResult->resource->id,
                        resourceName: $resourceResult->resource->name,
                        startAt: $interval->start,
                        endAt: $interval->end,
                    );
                }
            }
        }

        return $matches;
    }

    /**
     * @return list<Business>
     */
    private function resolveCandidateBusinesses(SavedSearch $savedSearch, SavedSearchCriteria $criteria): array
    {
        if ($savedSearch->business_id !== null) {
            $business = Business::query()
                ->publiclyVisible()
                ->find($savedSearch->business_id);

            return $business !== null ? [$business] : [];
        }

        $filters = $criteria->toDiscoveryFilters(
            perPage: (int) config('rezera.saved_searches.max_businesses_per_scan', 20),
        );

        $query = $this->discoveryQueryBuilder->build($filters);

        return $query->limit((int) config('rezera.saved_searches.max_businesses_per_scan', 20))->get()->all();
    }

    public function isDueForScheduledProcessing(SavedSearch $savedSearch): bool
    {
        if (! $savedSearch->alert_enabled || ! $savedSearch->availability_required) {
            return false;
        }

        if ($savedSearch->last_checked_at === null) {
            return true;
        }

        return match ($savedSearch->alert_frequency) {
            SavedSearchAlertFrequency::Instant => true,
            SavedSearchAlertFrequency::Daily => $savedSearch->last_checked_at->lte(now()->subDay()),
            SavedSearchAlertFrequency::Weekly => $savedSearch->last_checked_at->lte(now()->subWeek()),
        };
    }

    /**
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeEligibleForAlerts(Builder $query): void
    {
        $query
            ->where('alert_enabled', true)
            ->where('availability_required', true);
    }
}
