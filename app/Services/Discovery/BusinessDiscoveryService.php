<?php

namespace App\Services\Discovery;

use App\Contracts\Discovery\BusinessSearchProviderInterface;
use App\Services\Availability\BusinessHoursResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class BusinessDiscoveryService
{
    public function __construct(
        private readonly BusinessSearchProviderInterface $searchProvider,
        private readonly BusinessHoursResolver $businessHoursResolver,
    ) {}

    public function discover(BusinessDiscoveryFilters $filters): LengthAwarePaginator
    {
        $paginator = $this->searchProvider->search($filters);

        if ($filters->includeOpenNow) {
            $paginator->getCollection()->transform(function ($business) {
                $business->setAttribute(
                    'open_now',
                    $this->businessHoursResolver->isOpenAt($business),
                );

                return $business;
            });
        }

        return $paginator;
    }
}
