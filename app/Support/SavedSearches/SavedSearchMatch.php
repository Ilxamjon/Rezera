<?php

namespace App\Support\SavedSearches;

use Carbon\CarbonImmutable;

final class SavedSearchMatch
{
    public function __construct(
        public readonly string $businessId,
        public readonly string $businessName,
        public readonly string $resourceId,
        public readonly string $resourceName,
        public readonly CarbonImmutable $startAt,
        public readonly CarbonImmutable $endAt,
    ) {}

    public function hash(): string
    {
        return hash('sha256', implode('|', [
            $this->businessId,
            $this->resourceId,
            $this->startAt->utc()->toIso8601String(),
            $this->endAt->utc()->toIso8601String(),
        ]));
    }
}
