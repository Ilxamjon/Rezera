<?php

namespace App\Services\Discovery;

use App\Domain\Discovery\Enums\BusinessDiscoverySort;
use App\Http\Requests\Api\V1\Business\BusinessDiscoveryRequest;

final class BusinessDiscoveryFilters
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $categoryId = null,
        public readonly ?string $categorySlug = null,
        public readonly ?string $city = null,
        public readonly ?string $district = null,
        public readonly ?string $region = null,
        public readonly ?string $countryCode = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?float $radiusKm = null,
        public readonly bool $openNow = false,
        public readonly bool $availableNow = false,
        public readonly ?string $availableDate = null,
        public readonly ?string $availableStartTime = null,
        public readonly ?string $availableEndTime = null,
        public readonly ?string $resourceCategoryId = null,
        public readonly ?string $resourceType = null,
        public readonly ?int $minPrice = null,
        public readonly ?int $maxPrice = null,
        public readonly BusinessDiscoverySort $sort = BusinessDiscoverySort::Newest,
        public readonly int $page = 1,
        public readonly int $perPage = 20,
        public readonly bool $includeOpenNow = false,
        public readonly bool $includeDistance = false,
        public readonly ?string $userId = null,
        public readonly bool $favoritesOnly = false,
        public readonly bool $includeFavoriteState = false,
    ) {}

    public static function fromRequest(BusinessDiscoveryRequest $request, ?string $userId = null): self
    {
        $latitude = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $longitude = $request->filled('longitude') ? (float) $request->input('longitude') : null;
        $hasGeo = $latitude !== null && $longitude !== null;

        $sort = $request->resolvedSort();

        if ($hasGeo && $sort === BusinessDiscoverySort::Newest && ! $request->filled('sort')) {
            $sort = BusinessDiscoverySort::Nearest;
        }

        return new self(
            search: $request->normalizedSearch(),
            categoryId: $request->resolvedCategoryId(),
            categorySlug: $request->filled('category') ? $request->string('category')->toString() : null,
            city: $request->filled('city') ? trim($request->string('city')->toString()) : null,
            district: $request->filled('district') ? trim($request->string('district')->toString()) : null,
            region: $request->filled('region') ? trim($request->string('region')->toString()) : null,
            countryCode: $request->filled('country_code') ? strtoupper(trim($request->string('country_code')->toString())) : null,
            latitude: $latitude,
            longitude: $longitude,
            radiusKm: $request->filled('radius') ? (float) $request->input('radius') : null,
            openNow: $request->boolean('open_now'),
            availableNow: $request->boolean('available_now'),
            availableDate: $request->filled('available_date') ? $request->string('available_date')->toString() : null,
            availableStartTime: $request->filled('available_start_time') ? $request->string('available_start_time')->toString() : null,
            availableEndTime: $request->filled('available_end_time') ? $request->string('available_end_time')->toString() : null,
            resourceCategoryId: $request->resolvedResourceCategoryId(),
            resourceType: $request->filled('resource_type') ? $request->string('resource_type')->toString() : null,
            minPrice: $request->filled('min_price') ? (int) $request->input('min_price') : null,
            maxPrice: $request->filled('max_price') ? (int) $request->input('max_price') : null,
            sort: $sort,
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(max((int) $request->input('per_page', 20), 1), 50),
            includeOpenNow: $request->boolean('open_now') || $request->boolean('include_open_now'),
            includeDistance: $hasGeo,
            userId: $userId,
            favoritesOnly: $userId !== null && $request->boolean('favorites'),
            includeFavoriteState: $userId !== null,
        );
    }

    public function hasGeoSearch(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function hasAvailabilityWindow(): bool
    {
        return $this->availableDate !== null
            && $this->availableStartTime !== null
            && $this->availableEndTime !== null;
    }
}
