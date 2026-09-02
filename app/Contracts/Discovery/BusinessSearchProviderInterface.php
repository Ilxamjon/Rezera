<?php

namespace App\Contracts\Discovery;

use App\Services\Discovery\BusinessDiscoveryFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BusinessSearchProviderInterface
{
    public function search(BusinessDiscoveryFilters $filters): LengthAwarePaginator;
}
