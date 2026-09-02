<?php

namespace App\Services\Analytics;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Models\ReservationSession;
use App\Services\Analytics\Concerns\BuildsReservationAnalyticsQueries;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Analytics\AnalyticsMetrics;

final class CheckInAnalyticsService
{
    use BuildsReservationAnalyticsQueries;

    /**
     * @return array<string, mixed>
     */
    public function metrics(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $reservations = $this->reservationQuery($business, $range, $filter);

        $eligible = (clone $reservations)
            ->whereIn('status', [
                ReservationStatus::Completed->value,
                ReservationStatus::CheckedIn->value,
                ReservationStatus::NoShow->value,
            ])
            ->count();

        $checkedIn = (clone $reservations)
            ->whereNotNull('checked_in_at')
            ->count();

        $noShow = (clone $reservations)
            ->where('status', ReservationStatus::NoShow->value)
            ->count();

        $delayAgg = (clone $reservations)
            ->whereNotNull('checked_in_at')
            ->selectRaw('avg(extract(epoch from (checked_in_at - start_at)) / 60) as avg_delay_minutes')
            ->selectRaw("sum(case when checked_in_at < start_at then 1 else 0 end) as early_arrivals")
            ->selectRaw("sum(case when checked_in_at > start_at then 1 else 0 end) as late_arrivals")
            ->first();

        $sessionAgg = ReservationSession::query()
            ->where('business_id', $business->id)
            ->where('started_at', '>=', $range->utcStart())
            ->where('started_at', '<=', $range->utcEnd())
            ->whereNotNull('ended_at')
            ->selectRaw('avg(extract(epoch from (ended_at - started_at)) / 60) as avg_actual_minutes')
            ->selectRaw('coalesce(sum(extract(epoch from (ended_at - started_at)) / 60), 0) as total_actual_minutes')
            ->first();

        $scheduledMinutes = (int) (clone $reservations)
            ->whereNotNull('checked_in_at')
            ->sum('duration_minutes');

        $actualMinutes = (int) round((float) ($sessionAgg->total_actual_minutes ?? 0));
        $overtimeMinutes = max(0, $actualMinutes - $scheduledMinutes);

        return [
            'check_in_rate' => AnalyticsMetrics::percent($checkedIn, $eligible),
            'no_show_rate' => AnalyticsMetrics::percent($noShow, $eligible),
            'average_arrival_delay_minutes' => $delayAgg->avg_delay_minutes !== null
                ? round((float) $delayAgg->avg_delay_minutes, 2)
                : null,
            'early_arrivals' => (int) ($delayAgg->early_arrivals ?? 0),
            'late_arrivals' => (int) ($delayAgg->late_arrivals ?? 0),
            'average_actual_session_minutes' => $sessionAgg->avg_actual_minutes !== null
                ? round((float) $sessionAgg->avg_actual_minutes, 2)
                : null,
            'scheduled_minutes' => $scheduledMinutes,
            'actual_minutes' => $actualMinutes,
            'overtime_minutes' => $overtimeMinutes,
        ];
    }
}
