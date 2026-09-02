<?php

namespace App\Services\Analytics;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Services\Analytics\Concerns\BuildsReservationAnalyticsQueries;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Analytics\AnalyticsMetrics;
use Illuminate\Support\Facades\DB;

final class ReservationAnalyticsService
{
    use BuildsReservationAnalyticsQueries;

    /**
     * @return array<string, mixed>
     */
    public function metrics(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $counts = $this->reservationStatusCounts($business, $range, $filter);

        $total = array_sum($counts);
        $completed = (int) ($counts[ReservationStatus::Completed->value] ?? 0);
        $cancelled = (int) ($counts[ReservationStatus::Cancelled->value] ?? 0);
        $noShow = (int) ($counts[ReservationStatus::NoShow->value] ?? 0);
        $checkedIn = (int) ($counts[ReservationStatus::CheckedIn->value] ?? 0);
        $confirmed = (int) ($counts[ReservationStatus::Confirmed->value] ?? 0);
        $pending = (int) ($counts[ReservationStatus::Pending->value] ?? 0);

        $eligibleForCheckIn = $completed + $checkedIn + $noShow;
        $eligibleForCompletion = $completed + $checkedIn + $noShow + $cancelled;

        return [
            'total' => $total,
            'completed' => $completed,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'checked_in' => $checkedIn,
            'cancelled' => $cancelled,
            'no_show' => $noShow,
            'check_in_rate' => AnalyticsMetrics::percent($checkedIn + $completed, $eligibleForCheckIn),
            'completion_rate' => AnalyticsMetrics::percent($completed, $eligibleForCompletion),
            'cancellation_rate' => AnalyticsMetrics::percent($cancelled, $total),
            'no_show_rate' => AnalyticsMetrics::percent($noShow, $eligibleForCheckIn),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trends(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $trunc = $filter->granularity->sqlTrunc();
        $timezone = $range->timezone;

        $rows = $this->reservationQuery($business, $range, $filter)
            ->selectRaw(
                "date_trunc(?, (start_at AT TIME ZONE 'UTC') AT TIME ZONE ?, 'UTC')::date as bucket",
                [$trunc, $timezone],
            )
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = ? then 1 else 0 end) as completed", [ReservationStatus::Completed->value])
            ->selectRaw("sum(case when status = ? then 1 else 0 end) as cancelled", [ReservationStatus::Cancelled->value])
            ->selectRaw("sum(case when status = ? then 1 else 0 end) as no_show", [ReservationStatus::NoShow->value])
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return [
            'granularity' => $filter->granularity->value,
            'data' => $rows->map(fn ($row) => [
                'date' => (string) $row->bucket,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
                'cancelled' => (int) $row->cancelled,
                'no_show' => (int) $row->no_show,
            ])->values()->all(),
        ];
    }
}
