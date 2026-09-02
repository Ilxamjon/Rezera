<?php

namespace App\Services\Availability;

use App\Domain\Availability\Enums\AvailabilityReason;
use App\Models\Business;
use App\Support\Time\TimeInterval;
use Carbon\CarbonImmutable;

final class AvailabilityResult
{
    /**
     * @param  list<ResourceAvailabilityResult>  $resources
     */
    public function __construct(
        public readonly Business $business,
        public readonly CarbonImmutable $date,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly TimeInterval $requestedInterval,
        public readonly ?AvailabilityReason $requestLevelReason,
        public readonly array $resources,
    ) {}

    public function businessTimezone(): string
    {
        return $this->business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
    }
}
