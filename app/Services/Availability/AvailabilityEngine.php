<?php

namespace App\Services\Availability;

use App\Domain\Availability\Enums\AvailabilityReason;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Resource;
use App\Services\Reservations\ReservationRulesEngine;
use App\Support\Time\TimeInterval;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class AvailabilityEngine
{
    public function __construct(
        private readonly BusinessHoursResolver $businessHoursResolver,
        private readonly OccupyingReservationProvider $occupyingReservationProvider,
        private readonly ReservationRulesEngine $rulesEngine,
    ) {}

    public function calculate(AvailabilityQuery $query): AvailabilityResult
    {
        $timezone = $query->business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $requestedInterval = $this->buildRequestedInterval($query->date, $query->startTime, $query->endTime, $timezone);

        $requestLevelReason = $this->resolveRequestLevelReason($query, $requestedInterval, $timezone);
        $resources = $this->loadResources($query);

        $occupyingByResource = $requestLevelReason === null
            ? $this->occupyingReservationProvider->intervalsByResource(
                $resources->pluck('id')->all(),
                $requestedInterval,
            )
            : collect();

        $results = [];

        foreach ($resources as $resource) {
            $results[] = $this->evaluateResource(
                $resource,
                $query,
                $requestedInterval,
                $requestLevelReason,
                $occupyingByResource->get($resource->id, []),
            );
        }

        return new AvailabilityResult(
            business: $query->business,
            date: $query->date,
            startTime: $query->startTime,
            endTime: $query->endTime,
            requestedInterval: $requestedInterval,
            requestLevelReason: $requestLevelReason,
            resources: $results,
        );
    }

    public function buildRequestedInterval(
        CarbonImmutable $date,
        string $startTime,
        string $endTime,
        string $timezone,
    ): TimeInterval {
        return TimeInterval::fromLocalTimes($date, $startTime, $endTime, $timezone);
    }

    private function resolveRequestLevelReason(
        AvailabilityQuery $query,
        TimeInterval $requestedInterval,
        string $timezone,
    ): ?AvailabilityReason {
        $now = CarbonImmutable::now($timezone);

        if ($requestedInterval->end->lessThanOrEqualTo($now)) {
            return AvailabilityReason::Past;
        }

        if ($requestedInterval->start->lessThan($now)) {
            return AvailabilityReason::Past;
        }

        if ($this->businessHoursResolver->isClosedOnDate($query->business, $query->date)) {
            return AvailabilityReason::BusinessClosed;
        }

        $openIntervals = $this->businessHoursResolver->openIntervalsForDate($query->business, $query->date);

        if (! $requestedInterval->isFullyCoveredBy($openIntervals)) {
            return AvailabilityReason::OutsideWorkingHours;
        }

        $query->business->loadMissing('bookingPolicy');

        return $this->rulesEngine->matchAdvanceAvailabilityViolation(
            business: $query->business,
            interval: $requestedInterval,
            timezone: $timezone,
        );
    }

    /**
     * @param  list<TimeInterval>  $occupyingIntervals
     */
    private function evaluateResource(
        Resource $resource,
        AvailabilityQuery $query,
        TimeInterval $requestedInterval,
        ?AvailabilityReason $requestLevelReason,
        array $occupyingIntervals,
    ): ResourceAvailabilityResult {
        if ($requestLevelReason !== null) {
            return new ResourceAvailabilityResult($resource, $requestLevelReason);
        }

        $resourceRuleReason = $this->rulesEngine->matchDurationAvailabilityViolation(
            business: $query->business,
            interval: $requestedInterval,
            timezone: $query->business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent'),
            resource: $resource,
        );

        if ($resourceRuleReason !== null) {
            return new ResourceAvailabilityResult($resource, $resourceRuleReason);
        }

        if ($resource->status === ResourceStatus::Inactive) {
            return new ResourceAvailabilityResult($resource, AvailabilityReason::Inactive);
        }

        if ($resource->status === ResourceStatus::Maintenance) {
            return new ResourceAvailabilityResult($resource, AvailabilityReason::Maintenance);
        }

        foreach ($occupyingIntervals as $occupyingInterval) {
            if ($occupyingInterval->overlaps($requestedInterval)) {
                return new ResourceAvailabilityResult($resource, AvailabilityReason::Booked);
            }
        }

        return new ResourceAvailabilityResult($resource, AvailabilityReason::Available);
    }

    private function loadResources(AvailabilityQuery $query)
    {
        $builder = Resource::query()
            ->forBusiness($query->business->id)
            ->with(['group'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($query->resourceId !== null) {
            $builder->where('id', $query->resourceId);
        }

        if ($query->resourceCategoryId !== null) {
            $builder->where('resource_group_id', $query->resourceCategoryId);
        }

        if (! $query->managementView) {
            $builder->publiclyBookable();
        } elseif (! $query->includeUnavailable) {
            $builder->where('status', ResourceStatus::Active);
        }

        $resources = $builder->get();

        if ($query->resourceId !== null && $resources->isEmpty()) {
            throw ValidationException::withMessages([
                'resource_id' => [__('availability.resource_not_found')],
            ]);
        }

        if ($query->resourceCategoryId !== null && $query->resourceId === null) {
            $categoryExists = $query->business->resourceGroups()
                ->where('id', $query->resourceCategoryId)
                ->whereNull('deleted_at')
                ->exists();

            if (! $categoryExists) {
                throw ValidationException::withMessages([
                    'category_id' => [__('availability.category_not_found')],
                ]);
            }
        }

        return $resources;
    }
}
