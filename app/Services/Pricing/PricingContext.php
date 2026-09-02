<?php

namespace App\Services\Pricing;

use App\Models\Business;
use App\Models\Resource;
use Carbon\CarbonImmutable;

final class PricingContext
{
    public function __construct(
        public readonly Business $business,
        public readonly Resource $resource,
        public readonly CarbonImmutable $startAtUtc,
        public readonly CarbonImmutable $endAtUtc,
        public readonly string $timezone,
    ) {}

    public function localStart(): CarbonImmutable
    {
        return $this->startAtUtc->timezone($this->timezone);
    }

    public function localEnd(): CarbonImmutable
    {
        return $this->endAtUtc->timezone($this->timezone);
    }

    public function durationMinutes(): int
    {
        return (int) $this->startAtUtc->diffInMinutes($this->endAtUtc);
    }
}
