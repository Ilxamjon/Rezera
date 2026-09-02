<?php

namespace App\Services\Reservations;

use App\Models\Business;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class ReservationQueryBuilder
{
    private const MAX_DATE_RANGE_DAYS = 90;

    /**
     * @var list<string>
     */
    private const HISTORICAL_STATUSES = [
        'cancelled',
        'completed',
        'no_show',
        'rejected',
        'expired',
    ];

    /**
     * @var list<string>
     */
    private const ACTIVE_STATUSES = [
        'pending',
        'confirmed',
        'checked_in',
    ];

    public function forBusiness(Business $business, ReservationListFilters $filters): Builder
    {
        $timezone = $filters->timezone ?? $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');

        $query = Reservation::query()
            ->where('business_id', $business->id)
            ->with(['resource.group', 'customer']);

        $this->applyCommonFilters($query, $filters, $timezone, $business->id);
        $this->applyBusinessScope($query, $filters, $timezone);
        $this->applySorting($query, $filters, $this->defaultBusinessSortDirection($filters));

        return $query;
    }

    public function forCustomer(string $customerId, ReservationListFilters $filters): Builder
    {
        $query = Reservation::query()
            ->where('customer_id', $customerId)
            ->with(['business', 'resource.group']);

        $this->applyCommonFilters($query, $filters, $filters->timezone, null);
        $this->applyCustomerScope($query, $filters);
        $this->applySorting($query, $filters, $this->defaultCustomerSortDirection($filters));

        return $query;
    }

    private function applyCommonFilters(
        Builder $query,
        ReservationListFilters $filters,
        ?string $timezone,
        ?string $businessId,
    ): void {
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->businessId !== null) {
            $query->where('business_id', $filters->businessId);
        }

        if ($filters->resourceId !== null) {
            if ($businessId !== null) {
                $query->whereHas('resource', fn (Builder $builder) => $builder
                    ->where('id', $filters->resourceId)
                    ->where('business_id', $businessId));
            } else {
                $query->where('resource_id', $filters->resourceId);
            }
        }

        if ($filters->search !== null) {
            $term = '%'.addcslashes($filters->search, '%_\\').'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->where('reservation_number', 'ilike', $term)
                    ->orWhere('customer_name_snapshot', 'ilike', $term)
                    ->orWhere('customer_phone_snapshot', 'ilike', $term);
            });
        }

        if ($filters->date !== null && $timezone !== null) {
            $this->applyDateOverlap($query, $filters->date, $timezone);
        }

        if (($filters->from !== null || $filters->to !== null) && $timezone !== null) {
            $this->applyDateRange($query, $filters->from, $filters->to, $timezone);
        }
    }

    private function applyBusinessScope(Builder $query, ReservationListFilters $filters, string $timezone): void
    {
        if ($filters->scope === 'today') {
            $this->applyTodayOverlap($query, $timezone);

            return;
        }

        if ($filters->scope === 'upcoming') {
            $this->applyUpcoming($query, $filters->status);

            return;
        }

        if ($filters->scope === 'past') {
            $this->applyPast($query);
        }
    }

    private function applyCustomerScope(Builder $query, ReservationListFilters $filters): void
    {
        if ($filters->scope === 'upcoming') {
            $this->applyUpcoming($query, $filters->status);

            return;
        }

        if ($filters->scope === 'past') {
            $this->applyPast($query);
        }
    }

    private function applyUpcoming(Builder $query, ?string $explicitStatus): void
    {
        $query->where('end_at', '>', now());

        if ($explicitStatus === null) {
            $query->whereIn('status', self::ACTIVE_STATUSES);
        }
    }

    private function applyPast(Builder $query): void
    {
        $query->where(function (Builder $builder): void {
            $builder
                ->where('end_at', '<=', now())
                ->orWhereIn('status', self::HISTORICAL_STATUSES);
        });
    }

    private function applyTodayOverlap(Builder $query, string $timezone): void
    {
        $start = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $end = CarbonImmutable::now($timezone)->endOfDay()->utc();

        $query
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start);
    }

    private function applyDateOverlap(Builder $query, string $date, string $timezone): void
    {
        $start = CarbonImmutable::parse($date, $timezone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($date, $timezone)->endOfDay()->utc();

        $query
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start);
    }

    private function applyDateRange(Builder $query, ?string $from, ?string $to, string $timezone): void
    {
        $rangeStart = $from !== null
            ? CarbonImmutable::parse($from, $timezone)->startOfDay()->utc()
            : null;
        $rangeEnd = $to !== null
            ? CarbonImmutable::parse($to, $timezone)->endOfDay()->utc()
            : null;

        if ($rangeStart !== null && $rangeEnd !== null) {
            $days = $rangeStart->diffInDays($rangeEnd);
            if ($days > self::MAX_DATE_RANGE_DAYS) {
                abort(422, __('reservations.date_range_too_large', ['days' => self::MAX_DATE_RANGE_DAYS]));
            }
        }

        if ($rangeStart !== null) {
            $query->where('end_at', '>', $rangeStart);
        }

        if ($rangeEnd !== null) {
            $query->where('start_at', '<', $rangeEnd);
        }
    }

    private function applySorting(Builder $query, ReservationListFilters $filters, string $defaultDirection): void
    {
        $direction = $filters->sort === 'start_at'
            ? $filters->sortDirection
            : $defaultDirection;

        $query->orderBy('start_at', $direction === 'desc' ? 'desc' : 'asc');
    }

    private function defaultBusinessSortDirection(ReservationListFilters $filters): string
    {
        if (in_array($filters->scope, ['today', 'upcoming'], true)) {
            return 'asc';
        }

        if ($filters->scope === 'past' || $filters->from !== null || $filters->to !== null) {
            return 'desc';
        }

        return 'desc';
    }

    private function defaultCustomerSortDirection(ReservationListFilters $filters): string
    {
        if ($filters->scope === 'past') {
            return 'desc';
        }

        return 'asc';
    }
}
