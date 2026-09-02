<?php

namespace App\Services\Analytics;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Models\Reservation;
use App\Services\Analytics\Concerns\BuildsReservationAnalyticsQueries;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Analytics\AnalyticsMetrics;
use Illuminate\Support\Facades\DB;

final class CustomerAnalyticsService
{
    use BuildsReservationAnalyticsQueries;

    /**
     * @return array<string, mixed>
     */
    public function metrics(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $periodStartUtc = $range->utcStart()->toDateTimeString();
        $periodEndUtc = $range->utcEnd()->toDateTimeString();

        $customersInPeriod = $this->reservationQuery($business, $range, $filter)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        $activeCustomers = $this->reservationQuery($business, $range, $filter)
            ->where('status', ReservationStatus::Completed->value)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        $newCustomers = Reservation::query()
            ->where('business_id', $business->id)
            ->where('status', ReservationStatus::Completed->value)
            ->whereNotNull('customer_id')
            ->whereBetween('completed_at', [$periodStartUtc, $periodEndUtc])
            ->whereNotExists(function ($query) use ($business, $periodStartUtc): void {
                $query->select(DB::raw(1))
                    ->from('reservations as prior')
                    ->whereColumn('prior.customer_id', 'reservations.customer_id')
                    ->where('prior.business_id', $business->id)
                    ->where('prior.status', ReservationStatus::Completed->value)
                    ->where('prior.completed_at', '<', $periodStartUtc);
            })
            ->distinct('customer_id')
            ->count('customer_id');

        $returningCustomers = Reservation::query()
            ->where('business_id', $business->id)
            ->where('status', ReservationStatus::Completed->value)
            ->whereNotNull('customer_id')
            ->whereBetween('completed_at', [$periodStartUtc, $periodEndUtc])
            ->whereExists(function ($query) use ($business, $periodStartUtc): void {
                $query->select(DB::raw(1))
                    ->from('reservations as prior')
                    ->whereColumn('prior.customer_id', 'reservations.customer_id')
                    ->where('prior.business_id', $business->id)
                    ->where('prior.status', ReservationStatus::Completed->value)
                    ->where('prior.completed_at', '<', $periodStartUtc);
            })
            ->distinct('customer_id')
            ->count('customer_id');

        $completedCount = $this->reservationQuery($business, $range, $filter)
            ->where('status', ReservationStatus::Completed->value)
            ->count();

        $valueAgg = $this->reservationQuery($business, $range, $filter)
            ->where('status', ReservationStatus::Completed->value)
            ->selectRaw('coalesce(sum(total_amount), 0) as total_value')
            ->first();

        return [
            'total_customers' => $customersInPeriod,
            'active_customers' => $activeCustomers,
            'new_customers' => $newCustomers,
            'returning_customers' => $returningCustomers,
            'customers_with_completed_reservations' => $activeCustomers,
            'average_reservations_per_customer' => AnalyticsMetrics::average($completedCount, $activeCustomers),
            'average_customer_value' => AnalyticsMetrics::average((int) ($valueAgg->total_value ?? 0), $activeCustomers),
            'repeat_rate_percent' => AnalyticsMetrics::percent($returningCustomers, $newCustomers + $returningCustomers),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function topCustomers(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $allowedSorts = [
            'reservation_count' => 'reservation_count',
            'reservation_value' => 'reservation_value',
            'paid_amount' => 'paid_amount',
        ];

        $sort = $allowedSorts[$filter->sort ?? 'reservation_value'] ?? 'reservation_value';
        $direction = $filter->direction === 'asc' ? 'asc' : 'desc';

        $rows = $this->reservationQuery($business, $range, $filter)
            ->whereNotNull('customer_id')
            ->where('status', ReservationStatus::Completed->value)
            ->join('users', 'users.id', '=', 'reservations.customer_id')
            ->select('users.id', 'users.name')
            ->selectRaw('count(*) as reservation_count')
            ->selectRaw('coalesce(sum(reservations.total_amount), 0) as reservation_value')
            ->groupBy('users.id', 'users.name')
            ->orderBy($sort, $direction)
            ->limit($filter->limit)
            ->get();

        return [
            'sort' => $sort,
            'direction' => $direction,
            'data' => $rows->map(fn ($row) => [
                'customer_id' => $row->id,
                'name' => $row->name,
                'reservation_count' => (int) $row->reservation_count,
                'reservation_value' => (int) $row->reservation_value,
            ])->values()->all(),
        ];
    }
}
