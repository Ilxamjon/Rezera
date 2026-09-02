<?php

namespace App\Services\Discovery;

use App\Domain\Discovery\Enums\BusinessDiscoverySort;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class BusinessDiscoveryQueryBuilder
{
    public function __construct(
        private readonly BusinessOpenNowFilter $openNowFilter,
        private readonly BusinessDiscoveryAvailabilityFilter $availabilityFilter,
        private readonly \App\Services\Reviews\ReviewRatingService $reviewRatingService,
    ) {}

    /**
     * @return Builder<Business>
     */
    public function build(BusinessDiscoveryFilters $filters): Builder
    {
        $query = Business::query()
            ->publiclyVisible()
            ->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('is_active', true));

        $this->applySearch($query, $filters);
        $this->applyCategoryFilter($query, $filters);
        $this->applyLocationFilters($query, $filters);
        $this->applyFavoriteFilters($query, $filters);
        $this->applyPriceFilters($query, $filters);

        if ($filters->hasGeoSearch()) {
            $this->applyGeoSearch($query, $filters);
        }

        if ($filters->openNow) {
            $this->openNowFilter->apply($query);
        }

        if ($filters->availableNow) {
            $this->availabilityFilter->applyAvailableNow($query);
        }

        if ($filters->hasAvailabilityWindow()) {
            $this->availabilityFilter->applyAvailabilityWindow(
                $query,
                $filters->availableDate,
                $filters->availableStartTime,
                $filters->availableEndTime,
                $filters->resourceCategoryId,
                $filters->resourceType,
            );
        } elseif ($filters->resourceCategoryId !== null || $filters->resourceType !== null) {
            $this->applyResourceExistenceFilter($query, $filters->resourceCategoryId, $filters->resourceType);
        }

        if ($filters->sort === BusinessDiscoverySort::Popular) {
            $query->withCount(['reservations as popularity_score' => function (Builder $reservationQuery): void {
                $reservationQuery
                    ->where('created_at', '>=', now()->subDays((int) config('rezera.discovery.popularity_days', 30)))
                    ->whereIn('status', [
                        ReservationStatus::Confirmed->value,
                        ReservationStatus::Completed->value,
                        ReservationStatus::CheckedIn->value,
                    ]);
            }]);
        }

        $this->applySorting($query, $filters);

        $query->with([
            'category',
        ]);

        if ($filters->includeFavoriteState && $filters->userId !== null) {
            $query->withExists([
                'favorites as is_favorite' => fn (Builder $favoriteQuery): Builder => $favoriteQuery
                    ->where('user_id', $filters->userId),
            ]);
        }

        $query->withCount([
            'resources as active_resources_count' => fn (Builder $resourceQuery): Builder => $resourceQuery
                ->where('status', ResourceStatus::Active)
                ->whereNull('deleted_at'),
        ]);

        $query->addSelect([
            'price_from' => DB::table('resources')
                ->selectRaw('MIN(hourly_rate_amount)')
                ->whereColumn('resources.business_id', 'businesses.id')
                ->where('status', ResourceStatus::Active->value)
                ->whereNull('deleted_at'),
        ]);

        $this->reviewRatingService->applyAggregates($query);

        return $query;
    }

    /**
     * @param  Builder<Business>  $query
     */
    private function applySearch(Builder $query, BusinessDiscoveryFilters $filters): void
    {
        if ($filters->search === null) {
            return;
        }

        $term = '%'.addcslashes($filters->search, '%_\\').'%';

        $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('name', 'ilike', $term)
                ->orWhere('description', 'ilike', $term)
                ->orWhere('address_line', 'ilike', $term)
                ->orWhere('city', 'ilike', $term)
                ->orWhere('district', 'ilike', $term)
                ->orWhere('region', 'ilike', $term)
                ->orWhereRaw('translations::text ILIKE ?', [$term]);
        });
    }

    /**
     * @param  Builder<Business>  $query
     */
    private function applyCategoryFilter(Builder $query, BusinessDiscoveryFilters $filters): void
    {
        if ($filters->categoryId !== null) {
            $query->where('category_id', $filters->categoryId);
        }
    }

    /**
     * @param  Builder<Business>  $query
     */
    private function applyFavoriteFilters(Builder $query, BusinessDiscoveryFilters $filters): void
    {
        if ($filters->favoritesOnly && $filters->userId !== null) {
            $query->whereHas('favorites', fn (Builder $favoriteQuery): Builder => $favoriteQuery
                ->where('user_id', $filters->userId));
        }
    }

    /**
     * @param  Builder<Business>  $query
     */
    private function applyLocationFilters(Builder $query, BusinessDiscoveryFilters $filters): void
    {
        if ($filters->city !== null) {
            $query->where('city', 'ilike', $filters->city);
        }

        if ($filters->district !== null) {
            $query->where('district', 'ilike', $filters->district);
        }

        if ($filters->region !== null) {
            $query->where('region', 'ilike', $filters->region);
        }

        if ($filters->countryCode !== null) {
            $query->where('country_code', $filters->countryCode);
        }
    }

    /**
     * @param  Builder<Business>  $query
     */
    private function applyGeoSearch(Builder $query, BusinessDiscoveryFilters $filters): void
    {
        $latitude = $filters->latitude;
        $longitude = $filters->longitude;
        $radiusKm = $filters->radiusKm ?? (float) config('rezera.discovery.default_radius_km', 10);

        $latDelta = $radiusKm / 111.0;
        $lonDelta = $radiusKm / (111.0 * max(cos(deg2rad($latitude)), 0.01));

        $haversine = '(6371 * acos(LEAST(1.0, GREATEST(-1.0,
            cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
            + sin(radians(?)) * sin(radians(latitude))
        ))))';

        $query
            ->select('businesses.*')
            ->selectRaw("{$haversine} as distance_km", [$latitude, $longitude, $latitude])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lonDelta, $longitude + $lonDelta])
            ->whereRaw("{$haversine} <= ?", [$latitude, $longitude, $latitude, $radiusKm]);
    }

    /**
     * @param  Builder<Business>  $query
     */
    private function applyResourceExistenceFilter(
        Builder $query,
        ?string $resourceCategoryId,
        ?string $resourceType,
    ): void {
        $query->whereExists(function ($subQuery) use ($resourceCategoryId, $resourceType): void {
            $subQuery
                ->selectRaw('1')
                ->from('resources')
                ->whereColumn('resources.business_id', 'businesses.id')
                ->where('resources.status', ResourceStatus::Active->value)
                ->whereNull('resources.deleted_at');

            if ($resourceCategoryId !== null) {
                $subQuery->where('resources.resource_group_id', $resourceCategoryId);
            }

            if ($resourceType !== null) {
                $subQuery->where('resources.resource_type', $resourceType);
            }
        });
    }

    /**
     * @param  Builder<Business>  $query
     */
    private function applyPriceFilters(Builder $query, BusinessDiscoveryFilters $filters): void
    {
        if ($filters->minPrice !== null) {
            $query->whereRaw('(
                SELECT MIN(hourly_rate_amount)
                FROM resources
                WHERE business_id = businesses.id
                AND status = ?
                AND deleted_at IS NULL
            ) >= ?', [ResourceStatus::Active->value, $filters->minPrice]);
        }

        if ($filters->maxPrice !== null) {
            $query->whereRaw('(
                SELECT MIN(hourly_rate_amount)
                FROM resources
                WHERE business_id = businesses.id
                AND status = ?
                AND deleted_at IS NULL
            ) <= ?', [ResourceStatus::Active->value, $filters->maxPrice]);
        }
    }

    /**
     * @param  Builder<Business>  $query
     */
    private function applySorting(Builder $query, BusinessDiscoveryFilters $filters): void
    {
        match ($filters->sort) {
            BusinessDiscoverySort::Name => $query->orderBy('name'),
            BusinessDiscoverySort::Nearest => $query->orderBy('distance_km'),
            BusinessDiscoverySort::PriceLow => $query->orderByRaw('price_from ASC NULLS LAST'),
            BusinessDiscoverySort::PriceHigh => $query->orderByRaw('price_from DESC NULLS LAST'),
            BusinessDiscoverySort::Popular => $query->orderByDesc('popularity_score'),
            BusinessDiscoverySort::Rating => $query
                ->orderByRaw('businesses.rating_average DESC NULLS LAST')
                ->orderByDesc('businesses.rating_count'),
            BusinessDiscoverySort::Relevance => $filters->search !== null
                ? $query->orderByRaw('CASE WHEN name ILIKE ? THEN 0 ELSE 1 END', [$filters->search.'%'])
                    ->orderBy('name')
                : $query->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };
    }
}
