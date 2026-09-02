<?php

namespace App\Services\Availability;

use App\Domain\Availability\Enums\AvailabilityReason;
use App\Models\Resource;

final class ResourceAvailabilityResult
{
    public function __construct(
        public readonly Resource $resource,
        public readonly AvailabilityReason $reason,
    ) {}

    public function isAvailable(): bool
    {
        return $this->reason === AvailabilityReason::Available;
    }

    public function status(): string
    {
        return $this->isAvailable() ? 'available' : 'unavailable';
    }
}
