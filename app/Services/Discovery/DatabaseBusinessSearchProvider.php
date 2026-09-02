<?php

namespace App\Services\Discovery;

use App\Contracts\Discovery\BusinessSearchProviderInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class DatabaseBusinessSearchProvider implements BusinessSearchProviderInterface
{
    public function __construct(
        private readonly BusinessDiscoveryQueryBuilder $queryBuilder,
    ) {}

    public function search(BusinessDiscoveryFilters $filters): LengthAwarePaginator
    {
        $query = $this->queryBuilder->build($filters);

        return $query->paginate(
            perPage: $filters->perPage,
            page: $filters->page,
        );
    }
}
