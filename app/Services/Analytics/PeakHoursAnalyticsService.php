<?php

namespace App\Services\Analytics;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Services\Analytics\Concerns\BuildsReservationAnalyticsQueries;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;

final class PeakHoursAnalyticsService
{
    use BuildsReservationAnalyticsQueries;

    /**
     * @return array<string, mixed>
     */
    public function peakHours(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $timezone = $range->timezone;

        $rows = $this->reservationQuery($business, $range, $filter)
            ->selectRaw(
                "extract(isodow from (start_at AT TIME ZONE 'UTC') AT TIME ZONE ?) as weekday",
                [$timezone],
            )
            ->selectRaw(
                "extract(hour from (start_at AT TIME ZONE 'UTC') AT TIME ZONE ?) as hour",
                [$timezone],
            )
            ->selectRaw('count(*) as reservations')
            ->selectRaw('coalesce(sum(duration_minutes), 0) as occupied_minutes')
            ->groupBy('weekday', 'hour')
            ->orderByDesc('reservations')
            ->limit($filter->limit)
            ->get();

        return [
            'data' => $rows->map(fn ($row) => [
                'weekday' => (int) $row->weekday,
                'hour' => (int) $row->hour,
                'reservations' => (int) $row->reservations,
                'occupied_minutes' => (int) $row->occupied_minutes,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function weekdays(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $timezone = $range->timezone;

        $rows = $this->reservationQuery($business, $range, $filter)
            ->selectRaw(
                "extract(isodow from (start_at AT TIME ZONE 'UTC') AT TIME ZONE ?) as weekday",
                [$timezone],
            )
            ->selectRaw('count(*) as reservations')
            ->selectRaw("sum(case when status = ? then 1 else 0 end) as completed", [ReservationStatus::Completed->value])
            ->selectRaw('coalesce(sum(total_amount), 0) as reservation_value')
            ->groupBy('weekday')
            ->orderBy('weekday')
            ->get();

        return [
            'data' => $rows->map(fn ($row) => [
                'weekday' => (int) $row->weekday,
                'reservations' => (int) $row->reservations,
                'completed' => (int) $row->completed,
                'reservation_value' => (int) $row->reservation_value,
                'average_value' => (int) $row->reservations > 0
                    ? (int) floor(((int) $row->reservation_value) / (int) $row->reservations)
                    : 0,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function hourlyOccupancy(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $timezone = $range->timezone;

        $rows = $this->reservationQuery($business, $range, $filter)
            ->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::CheckedIn->value,
                ReservationStatus::Completed->value,
            ])
            ->selectRaw(
                "extract(hour from (start_at AT TIME ZONE 'UTC') AT TIME ZONE ?) as hour",
                [$timezone],
            )
            ->selectRaw('count(*) as reservations')
            ->selectRaw('coalesce(sum(duration_minutes), 0) as booked_minutes')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return [
            'data' => $rows->map(fn ($row) => [
                'hour' => (int) $row->hour,
                'reservations' => (int) $row->reservations,
                'booked_minutes' => (int) $row->booked_minutes,
            ])->values()->all(),
        ];
    }
}
