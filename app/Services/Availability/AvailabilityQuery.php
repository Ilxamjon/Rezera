<?php

namespace App\Services\Availability;

use App\Models\Business;
use Carbon\CarbonImmutable;

final class AvailabilityQuery
{
    public function __construct(
        public readonly Business $business,
        public readonly CarbonImmutable $date,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly ?string $resourceCategoryId = null,
        public readonly ?string $resourceId = null,
        public readonly bool $includeUnavailable = false,
        public readonly bool $managementView = false,
    ) {}
}
