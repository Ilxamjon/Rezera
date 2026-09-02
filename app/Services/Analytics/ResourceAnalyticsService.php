<?php

namespace App\Services\Analytics;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Resource;
use App\Services\Analytics\Concerns\BuildsReservationAnalyticsQueries;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Analytics\AnalyticsMetrics;
use Carbon\CarbonImmutable;

final class ResourceAnalyticsService
{
    use BuildsReservationAnalyticsQueries;

    /**
     * @return array<string, mixed>
     */
    public function summary(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $resourceCounts = Resource::query()
            ->where('business_id', $business->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $availableMinutes = $this->estimateAvailableMinutes($business, $range);
        $bookedMinutes = (int) $this->reservationQuery($business, $range, $filter)
            ->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::CheckedIn->value,
                ReservationStatus::Completed->value,
            ])
            ->sum('duration_minutes');

        return [
            'total_resources' => (int) $resourceCounts->sum(),
            'active_resources' => (int) ($resourceCounts[ResourceStatus::Active->value] ?? 0),
            'maintenance_resources' => (int) ($resourceCounts[ResourceStatus::Maintenance->value] ?? 0),
            'inactive_resources' => (int) ($resourceCounts[ResourceStatus::Inactive->value] ?? 0),
            'booked_minutes' => $bookedMinutes,
            'available_minutes' => $availableMinutes,
            'utilization_percent' => AnalyticsMetrics::percent($bookedMinutes, $availableMinutes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function byResource(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $rows = $this->reservationQuery($business, $range, $filter)
            ->join('resources', 'resources.id', '=', 'reservations.resource_id')
            ->select('resources.id', 'resources.name', 'resources.status')
            ->selectRaw('count(*) as reservations_total')
            ->selectRaw("sum(case when reservations.status = ? then 1 else 0 end) as completed", [ReservationStatus::Completed->value])
            ->selectRaw("sum(case when reservations.status = ? then 1 else 0 end) as cancelled", [ReservationStatus::Cancelled->value])
            ->selectRaw('coalesce(sum(reservations.duration_minutes), 0) as booked_minutes')
            ->selectRaw('coalesce(sum(reservations.total_amount), 0) as reservation_value')
            ->groupBy('resources.id', 'resources.name', 'resources.status')
            ->orderByDesc('reservations_total')
            ->get();

        $availablePerResource = $this->estimateAvailableMinutes($business, $range);
        $activeCount = max(1, Resource::query()
            ->where('business_id', $business->id)
            ->where('status', ResourceStatus::Active)
            ->count());
        $minutesPerResource = (int) floor($availablePerResource / $activeCount);

        return [
            'data' => $rows->map(fn ($row) => [
                'resource_id' => $row->id,
                'name' => $row->name,
                'status' => $row->status,
                'reservations_total' => (int) $row->reservations_total,
                'completed' => (int) $row->completed,
                'cancelled' => (int) $row->cancelled,
                'booked_minutes' => (int) $row->booked_minutes,
                'reservation_value' => (int) $row->reservation_value,
                'utilization_percent' => AnalyticsMetrics::percent((int) $row->booked_minutes, $minutesPerResource),
                'average_session_minutes' => AnalyticsMetrics::average((int) $row->booked_minutes, (int) $row->reservations_total),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ranking(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $allowedSorts = [
            'reservations_total' => 'reservations_total',
            'reservation_value' => 'reservation_value',
            'booked_minutes' => 'booked_minutes',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
        ];

        $sort = $allowedSorts[$filter->sort ?? 'reservations_total'] ?? 'reservations_total';
        $direction = $filter->direction === 'asc' ? 'asc' : 'desc';

        $rows = $this->reservationQuery($business, $range, $filter)
            ->join('resources', 'resources.id', '=', 'reservations.resource_id')
            ->select('resources.id', 'resources.name')
            ->selectRaw('count(*) as reservations_total')
            ->selectRaw("sum(case when reservations.status = ? then 1 else 0 end) as completed", [ReservationStatus::Completed->value])
            ->selectRaw("sum(case when reservations.status = ? then 1 else 0 end) as cancelled", [ReservationStatus::Cancelled->value])
            ->selectRaw('coalesce(sum(reservations.duration_minutes), 0) as booked_minutes')
            ->selectRaw('coalesce(sum(reservations.total_amount), 0) as reservation_value')
            ->groupBy('resources.id', 'resources.name')
            ->orderBy($sort, $direction)
            ->limit($filter->limit)
            ->get();

        return [
            'sort' => $sort,
            'direction' => $direction,
            'data' => $rows->map(fn ($row) => [
                'resource_id' => $row->id,
                'name' => $row->name,
                'reservations_total' => (int) $row->reservations_total,
                'completed' => (int) $row->completed,
                'cancelled' => (int) $row->cancelled,
                'booked_minutes' => (int) $row->booked_minutes,
                'reservation_value' => (int) $row->reservation_value,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function byCategory(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $rows = $this->reservationQuery($business, $range, $filter)
            ->join('resources', 'resources.id', '=', 'reservations.resource_id')
            ->join('resource_groups', 'resource_groups.id', '=', 'resources.resource_group_id')
            ->select('resource_groups.id', 'resource_groups.name')
            ->selectRaw('count(*) as reservations_total')
            ->selectRaw("sum(case when reservations.status = ? then 1 else 0 end) as cancelled", [ReservationStatus::Cancelled->value])
            ->selectRaw('coalesce(sum(reservations.duration_minutes), 0) as booked_minutes')
            ->selectRaw('coalesce(sum(reservations.total_amount), 0) as reservation_value')
            ->groupBy('resource_groups.id', 'resource_groups.name')
            ->orderByDesc('reservations_total')
            ->get();

        return [
            'data' => $rows->map(fn ($row) => [
                'category_id' => $row->id,
                'name' => $row->name,
                'reservations_total' => (int) $row->reservations_total,
                'cancelled' => (int) $row->cancelled,
                'booked_minutes' => (int) $row->booked_minutes,
                'reservation_value' => (int) $row->reservation_value,
                'average_reservation_value' => AnalyticsMetrics::average(
                    (int) $row->reservation_value,
                    (int) $row->reservations_total,
                ),
                'cancellation_rate' => AnalyticsMetrics::percent((int) $row->cancelled, (int) $row->reservations_total),
            ])->values()->all(),
        ];
    }

    private function estimateAvailableMinutes(Business $business, AnalyticsDateRange $range): int
    {
        $activeResources = Resource::query()
            ->where('business_id', $business->id)
            ->where('status', ResourceStatus::Active)
            ->count();

        if ($activeResources === 0) {
            return 0;
        }

        $hours = BusinessHour::query()
            ->where('business_id', $business->id)
            ->get()
            ->keyBy('weekday');

        $minutesPerDay = 0;
        $cursor = $range->fromLocal->startOfDay();
        $end = $range->toLocal->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $weekday = (int) $cursor->isoWeekday();
            $day = $hours->get($weekday);

            if ($day !== null && ! $day->is_closed) {
                if ($day->is_open_24h) {
                    $minutesPerDay += 1440;
                } elseif ($day->opens_at !== null && $day->closes_at !== null) {
                    $open = CarbonImmutable::parse($day->opens_at, $range->timezone);
                    $close = CarbonImmutable::parse($day->closes_at, $range->timezone);
                    if ($close->lessThanOrEqualTo($open)) {
                        $minutesPerDay += $open->diffInMinutes($close->addDay());
                    } else {
                        $minutesPerDay += $open->diffInMinutes($close);
                    }
                }
            }

            $cursor = $cursor->addDay();
        }

        $days = $range->fromLocal->startOfDay()->diffInDays($range->toLocal->startOfDay()) + 1;
        $averageDailyMinutes = $days > 0 ? (int) floor($minutesPerDay / $days) : 0;

        return $averageDailyMinutes * $activeResources * $days;
    }
}
