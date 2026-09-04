<?php

namespace App\Services\Analytics\Concerns;

use App\Models\Business;
use App\Models\Reservation;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;
use Illuminate\Database\Eloquent\Builder;

trait BuildsReservationAnalyticsQueries
{
    protected function reservationQuery(
        Business $business,
        AnalyticsDateRange $range,
        AnalyticsFilter $filter,
    ): Builder {
        $query = Reservation::query()
            ->where('reservations.business_id', $business->id)
            ->where('reservations.start_at', '<', $range->utcEnd())
            ->where('reservations.end_at', '>', $range->utcStart());

        if ($filter->resourceId !== null) {
            $query->where('resource_id', $filter->resourceId);
        }

        if ($filter->resourceCategoryId !== null) {
            $query->whereHas('resource', fn (Builder $builder) => $builder
                ->where('resource_group_id', $filter->resourceCategoryId));
        }

        if ($filter->reservationStatus !== null) {
            $query->where('status', $filter->reservationStatus);
        }

        if ($filter->customerId !== null) {
            $query->where('customer_id', $filter->customerId);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    protected function reservationStatusCounts(
        Business $business,
        AnalyticsDateRange $range,
        AnalyticsFilter $filter,
    ): array {
        return $this->reservationQuery($business, $range, $filter)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();
    }
}
