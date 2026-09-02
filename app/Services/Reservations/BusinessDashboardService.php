<?php

namespace App\Services\Reservations;

use App\Domain\Analytics\Enums\AnalyticsDatePreset;
use App\Domain\Reservations\Enums\ReservationSessionStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\ReservationSession;
use App\Services\Analytics\ReservationAnalyticsService;
use App\Services\Analytics\RevenueAnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;
use Carbon\CarbonImmutable;

final class BusinessDashboardService
{
    public function __construct(
        private readonly ReservationAnalyticsService $reservationAnalytics,
        private readonly RevenueAnalyticsService $revenueAnalytics,
        private readonly BusinessOperationsService $operations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Business $business, bool $includeFinancial = true, ?int $upcomingLimit = null): array
    {
        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $now = CarbonImmutable::now($timezone);
        $todayRange = AnalyticsDateRange::preset($business, AnalyticsDatePreset::Today);
        $filter = new AnalyticsFilter;

        $reservationMetrics = $this->reservationAnalytics->metrics($business, $todayRange, $filter);
        $upcomingCount = $this->upcomingCount($business);
        $limit = $this->resolveUpcomingLimit($upcomingLimit);

        $today = [
            'total' => (int) $reservationMetrics['total'],
            'pending' => (int) $reservationMetrics['pending'],
            'confirmed' => (int) $reservationMetrics['confirmed'],
            'checked_in' => (int) $reservationMetrics['checked_in'],
            'completed' => (int) $reservationMetrics['completed'],
            'cancelled' => (int) $reservationMetrics['cancelled'],
            'no_show' => (int) $reservationMetrics['no_show'],
            'active_sessions' => $this->activeSessionsCount($business),
        ];

        $revenueToday = null;
        if ($includeFinancial) {
            $revenueMetrics = $this->revenueAnalytics->metrics($business, $todayRange, $filter);
            $revenueToday = [
                'currency' => $revenueMetrics['currency'],
                'amount_paid' => (int) $revenueMetrics['amount_paid'],
                'net_reservation_value' => (int) $revenueMetrics['net_reservation_value'],
                'gross_reservation_value' => (int) $revenueMetrics['gross_reservation_value'],
                'discounts' => (int) $revenueMetrics['discounts'],
                'estimated_outstanding' => (int) $revenueMetrics['estimated_outstanding'],
            ];
        }

        return [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'timezone' => $timezone,
            ],
            'date' => $now->toDateString(),
            'generated_at' => $now->toIso8601String(),
            'today' => $today,
            'upcoming' => $upcomingCount,
            'operations' => [
                'active_sessions' => $today['active_sessions'],
                'overdue_check_ins' => $this->overdueCheckInsCount($business, $now, $timezone),
            ],
            'resources' => $this->operations->resourceStatusSnapshot($business),
            'revenue_today' => $revenueToday,
            'upcoming_reservations' => $this->upcomingReservations($business, $limit, $timezone),
        ];
    }

    private function resolveUpcomingLimit(?int $requested): int
    {
        $default = (int) config('business_dashboard.upcoming_limit_default', 10);
        $max = (int) config('business_dashboard.upcoming_limit_max', 25);

        if ($requested === null) {
            return min(max($default, 1), $max);
        }

        return min(max($requested, 1), $max);
    }

    private function activeSessionsCount(Business $business): int
    {
        return ReservationSession::query()
            ->where('business_id', $business->id)
            ->where('status', ReservationSessionStatus::Active->value)
            ->count();
    }

    private function upcomingCount(Business $business): int
    {
        return Reservation::query()
            ->where('business_id', $business->id)
            ->where('end_at', '>', now())
            ->whereIn('status', array_map(
                static fn (ReservationStatus $status): string => $status->value,
                ReservationStatus::occupying(),
            ))
            ->count();
    }

    private function overdueCheckInsCount(Business $business, CarbonImmutable $now, string $timezone): int
    {
        $dayStart = $now->startOfDay()->utc();
        $dayEnd = $now->endOfDay()->utc();

        return Reservation::query()
            ->where('business_id', $business->id)
            ->whereBetween('start_at', [$dayStart, $dayEnd])
            ->where('status', ReservationStatus::Confirmed->value)
            ->whereNull('checked_in_at')
            ->where('end_at', '<', $now->utc())
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcomingReservations(Business $business, int $limit, string $timezone): array
    {
        return Reservation::query()
            ->where('business_id', $business->id)
            ->where('start_at', '>', now())
            ->whereIn('status', array_map(
                static fn (ReservationStatus $status): string => $status->value,
                ReservationStatus::occupying(),
            ))
            ->with(['resource:id,name,code'])
            ->orderBy('start_at')
            ->limit($limit)
            ->get()
            ->map(function (Reservation $reservation) use ($timezone): array {
                $startLocal = CarbonImmutable::instance($reservation->start_at)->timezone($timezone);
                $endLocal = CarbonImmutable::instance($reservation->end_at)->timezone($timezone);

                return [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'start_at' => $reservation->start_at->toIso8601String(),
                    'end_at' => $reservation->end_at->toIso8601String(),
                    'start_time' => $startLocal->format('H:i'),
                    'end_time' => $endLocal->format('H:i'),
                    'duration_minutes' => $reservation->duration_minutes,
                    'status' => $reservation->status->value,
                    'resource' => [
                        'id' => $reservation->resource->id,
                        'name' => $reservation->resource->name,
                        'code' => $reservation->resource->code,
                    ],
                    'customer_name' => $reservation->customer_name_snapshot,
                ];
            })
            ->values()
            ->all();
    }
}
