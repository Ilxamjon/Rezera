<?php

namespace App\Services\Calendar;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\Resource;
use App\Services\Availability\BusinessHoursResolver;
use App\Services\Reservations\ReservationOperationalStatusResolver;
use App\Support\Calendar\CalendarDateRange;
use App\Support\Calendar\CalendarFilter;
use App\Support\Time\TimeInterval;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class BusinessCalendarService
{
    public function __construct(
        private readonly BusinessHoursResolver $businessHoursResolver,
        private readonly CalendarScheduleBuilder $scheduleBuilder,
        private readonly ReservationOperationalStatusResolver $operationalStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function day(Business $business, CalendarDateRange $range, CalendarFilter $filter): array
    {
        $day = $range->fromLocal->startOfDay();
        $viewWindow = TimeInterval::fromLocalTimes($day, '00:00', '24:00', $range->timezone);
        $resources = $this->loadResources($business, $filter);
        $reservationsByResource = $this->loadReservations($business, $range, $filter, $resources);
        $now = CarbonImmutable::now($range->timezone);
        $openIntervals = $this->businessHoursResolver->openIntervalsForDate($business, $day);

        return [
            ...$this->baseMeta($business, $range),
            'view' => 'day',
            'date' => $day->toDateString(),
            'is_closed' => $openIntervals === [],
            'working_hours' => $this->formatWorkingHours($openIntervals, $range->timezone),
            'resources' => $resources->map(function (Resource $resource) use (
                $viewWindow,
                $openIntervals,
                $reservationsByResource,
                $now,
                $filter,
            ): array {
                $reservations = $reservationsByResource->get($resource->id, collect());

                return [
                    'resource' => $this->formatResource($resource),
                    'blocks' => $this->scheduleBuilder->buildForResourceDay(
                        resource: $resource,
                        viewWindow: $viewWindow,
                        openIntervals: $openIntervals,
                        reservations: $reservations,
                        now: $now,
                        includeAvailableGaps: $filter->includeAvailableGaps,
                    ),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function week(Business $business, CalendarDateRange $range, CalendarFilter $filter): array
    {
        $resources = $this->loadResources($business, $filter);
        $reservationsByResource = $this->loadReservations($business, $range, $filter, $resources);

        $days = collect($range->days())->map(function (CarbonImmutable $day) use (
            $business,
            $filter,
            $resources,
            $reservationsByResource,
            $range,
        ): array {
            $openIntervals = $this->businessHoursResolver->openIntervalsForDate($business, $day);
            $dayReservations = $this->reservationsForDay($reservationsByResource, $day, $range->timezone);
            $statusCounts = $dayReservations->countBy(fn (Reservation $reservation): string => $reservation->status->value);
            $activeCount = $dayReservations
                ->filter(fn (Reservation $reservation): bool => $reservation->status === ReservationStatus::CheckedIn
                    || $reservation->checked_in_at !== null
                    || $reservation->activeSession !== null)
                ->count();

            $dayPayload = [
                'date' => $day->toDateString(),
                'weekday' => $day->dayOfWeekIso,
                'is_closed' => $openIntervals === [],
                'working_hours' => $this->formatWorkingHours($openIntervals, $range->timezone),
                'summary' => [
                    'reservations_total' => $dayReservations->count(),
                    'active_sessions' => $activeCount,
                    'by_status' => $statusCounts->all(),
                ],
            ];

            if ($filter->detailed) {
                $viewWindow = TimeInterval::fromLocalTimes($day, '00:00', '24:00', $range->timezone);
                $now = CarbonImmutable::now($range->timezone);

                $dayPayload['resources'] = $resources->map(function (Resource $resource) use (
                    $viewWindow,
                    $openIntervals,
                    $reservationsByResource,
                    $day,
                    $range,
                    $now,
                    $filter,
                ): array {
                    $reservations = $reservationsByResource
                        ->get($resource->id, collect())
                        ->filter(fn (Reservation $reservation): bool => $this->reservationOverlapsDay(
                            $reservation,
                            $day,
                            $range->timezone,
                        ));

                    return [
                        'resource' => $this->formatResource($resource),
                        'blocks' => $this->scheduleBuilder->buildForResourceDay(
                            resource: $resource,
                            viewWindow: $viewWindow,
                            openIntervals: $openIntervals,
                            reservations: $reservations,
                            now: $now,
                            includeAvailableGaps: $filter->includeAvailableGaps,
                        ),
                    ];
                })->values()->all();
            }

            return $dayPayload;
        })->all();

        return [
            ...$this->baseMeta($business, $range),
            'view' => 'week',
            'days' => $days,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resourceSchedule(
        Business $business,
        Resource $resource,
        CalendarDateRange $range,
        CalendarFilter $filter,
    ): array {
        $resources = collect([$resource]);
        $reservationsByResource = $this->loadReservations($business, $range, $filter, $resources);
        $now = CarbonImmutable::now($range->timezone);

        $days = collect($range->days())->map(function (CarbonImmutable $day) use (
            $business,
            $resource,
            $reservationsByResource,
            $range,
            $now,
            $filter,
        ): array {
            $openIntervals = $this->businessHoursResolver->openIntervalsForDate($business, $day);
            $viewWindow = TimeInterval::fromLocalTimes($day, '00:00', '24:00', $range->timezone);
            $reservations = $reservationsByResource
                ->get($resource->id, collect())
                ->filter(fn (Reservation $reservation): bool => $this->reservationOverlapsDay(
                    $reservation,
                    $day,
                    $range->timezone,
                ));

            return [
                'date' => $day->toDateString(),
                'weekday' => $day->dayOfWeekIso,
                'is_closed' => $openIntervals === [],
                'working_hours' => $this->formatWorkingHours($openIntervals, $range->timezone),
                'blocks' => $this->scheduleBuilder->buildForResourceDay(
                    resource: $resource,
                    viewWindow: $viewWindow,
                    openIntervals: $openIntervals,
                    reservations: $reservations,
                    now: $now,
                    includeAvailableGaps: $filter->includeAvailableGaps,
                ),
            ];
        })->all();

        return [
            ...$this->baseMeta($business, $range),
            'view' => 'resource',
            'resource' => $this->formatResource($resource),
            'days' => $days,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function timeline(Business $business, CalendarDateRange $range, CalendarFilter $filter): array
    {
        $resources = $this->loadResources($business, $filter);
        $reservations = $this->loadReservations($business, $range, $filter, $resources)
            ->flatten(1)
            ->sortBy('start_at')
            ->values();
        $now = CarbonImmutable::now($range->timezone);

        return [
            ...$this->baseMeta($business, $range),
            'view' => 'timeline',
            'items' => $reservations->map(function (Reservation $reservation) use ($now, $range): array {
                $startLocal = CarbonImmutable::instance($reservation->start_at)->timezone($range->timezone);
                $endLocal = CarbonImmutable::instance($reservation->end_at)->timezone($range->timezone);

                return [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'status' => $reservation->status->value,
                    'operational_status' => $this->operationalStatus->resolve($reservation, $now)->value,
                    'start_at' => $reservation->start_at->toIso8601String(),
                    'end_at' => $reservation->end_at->toIso8601String(),
                    'start_time' => $startLocal->format('H:i'),
                    'end_time' => $endLocal->format('H:i'),
                    'date' => $startLocal->toDateString(),
                    'duration_minutes' => $reservation->duration_minutes,
                    'customer' => [
                        'id' => $reservation->customer_id,
                        'name' => $reservation->customer_name_snapshot,
                        'phone' => $reservation->customer_phone_snapshot,
                    ],
                    'resource' => $this->formatResource($reservation->resource),
                ];
            })->all(),
        ];
    }

    /**
     * @return Collection<int, Resource>
     */
    private function loadResources(Business $business, CalendarFilter $filter): Collection
    {
        $query = Resource::query()
            ->forBusiness($business->id)
            ->with(['group'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($filter->resourceId !== null) {
            $query->where('id', $filter->resourceId);
        }

        if ($filter->resourceCategoryId !== null) {
            $query->where('resource_group_id', $filter->resourceCategoryId);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Resource>  $resources
     * @return Collection<string, Collection<int, Reservation>>
     */
    private function loadReservations(
        Business $business,
        CalendarDateRange $range,
        CalendarFilter $filter,
        Collection $resources,
    ): Collection {
        if ($resources->isEmpty()) {
            return collect();
        }

        $query = Reservation::query()
            ->where('business_id', $business->id)
            ->whereIn('resource_id', $resources->pluck('id'))
            ->where('start_at', '<', $range->utcEnd())
            ->where('end_at', '>', $range->utcStart())
            ->with(['customer:id,name,phone', 'resource.group', 'activeSession'])
            ->orderBy('start_at');

        if (! $filter->includeCancelled) {
            $query->whereNotIn('status', [
                ReservationStatus::Cancelled->value,
                ReservationStatus::Rejected->value,
                ReservationStatus::Expired->value,
            ]);
        }

        return $query->get()->groupBy('resource_id');
    }

    /**
     * @param  Collection<string, Collection<int, Reservation>>  $reservationsByResource
     * @return Collection<int, Reservation>
     */
    private function reservationsForDay(
        Collection $reservationsByResource,
        CarbonImmutable $day,
        string $timezone,
    ): Collection {
        return $reservationsByResource
            ->flatten(1)
            ->filter(fn (Reservation $reservation): bool => $this->reservationOverlapsDay($reservation, $day, $timezone))
            ->values();
    }

    private function reservationOverlapsDay(Reservation $reservation, CarbonImmutable $day, string $timezone): bool
    {
        $dayStart = $day->startOfDay()->utc();
        $dayEnd = $day->endOfDay()->utc();

        return $reservation->start_at < $dayEnd && $reservation->end_at > $dayStart;
    }

    /**
     * @param  list<TimeInterval>  $intervals
     * @return list<array{opens_at: string, closes_at: string}>
     */
    private function formatWorkingHours(array $intervals, string $timezone): array
    {
        return collect($intervals)->map(function (TimeInterval $interval) use ($timezone): array {
            $start = $interval->start->timezone($timezone);
            $end = $interval->end->timezone($timezone);

            return [
                'opens_at' => $start->format('H:i'),
                'closes_at' => $end->format('H:i') === '00:00' ? '24:00' : $end->format('H:i'),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatResource(Resource $resource): array
    {
        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'code' => $resource->code,
            'status' => $resource->status->value,
            'category' => $resource->group ? [
                'id' => $resource->group->id,
                'name' => $resource->group->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseMeta(Business $business, CalendarDateRange $range): array
    {
        return [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'timezone' => $range->timezone,
            ],
            'period' => $range->toPeriodArray(),
            'generated_at' => CarbonImmutable::now($range->timezone)->toIso8601String(),
        ];
    }
}
