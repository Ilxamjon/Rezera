<?php

namespace App\Services\Payments;

use App\Models\Business;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class PaymentListFilters
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $provider = null,
        public readonly ?string $reservationId = null,
        public readonly ?string $resourceId = null,
        public readonly ?string $date = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?string $search = null,
        public readonly ?string $timezone = null,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request, ?string $timezone = null): self
    {
        return new self(
            status: $request->filled('status') ? $request->string('status')->toString() : null,
            provider: $request->filled('provider') ? $request->string('provider')->toString() : null,
            reservationId: $request->filled('reservation_id') ? $request->string('reservation_id')->toString() : null,
            resourceId: $request->filled('resource_id') ? $request->string('resource_id')->toString() : null,
            date: $request->filled('date') ? $request->string('date')->toString() : null,
            from: $request->filled('date_from') ? $request->string('date_from')->toString() : null,
            to: $request->filled('date_to') ? $request->string('date_to')->toString() : null,
            search: $request->filled('search') ? $request->string('search')->toString() : null,
            timezone: $timezone,
        );
    }
}

final class PaymentQueryBuilder
{
    private const MAX_DATE_RANGE_DAYS = 90;

    public function forBusiness(Business $business, PaymentListFilters $filters): Builder
    {
        $timezone = $filters->timezone ?? $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');

        $query = Payment::query()
            ->where('business_id', $business->id)
            ->with(['reservation.resource.group', 'user']);

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->provider !== null) {
            $query->where('provider', $filters->provider);
        }

        if ($filters->reservationId !== null) {
            $query->where('reservation_id', $filters->reservationId);
        }

        if ($filters->resourceId !== null) {
            $query->whereHas('reservation', fn (Builder $builder) => $builder
                ->where('resource_id', $filters->resourceId)
                ->where('business_id', $business->id));
        }

        if ($filters->search !== null) {
            $term = '%'.addcslashes($filters->search, '%_\\').'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->where('payment_number', 'ilike', $term)
                    ->orWhereHas('reservation', fn (Builder $reservationQuery) => $reservationQuery
                        ->where('reservation_number', 'ilike', $term));
            });
        }

        if ($filters->date !== null) {
            $start = CarbonImmutable::parse($filters->date, $timezone)->startOfDay()->utc();
            $end = CarbonImmutable::parse($filters->date, $timezone)->endOfDay()->utc();
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($filters->from !== null || $filters->to !== null) {
            $rangeStart = $filters->from !== null
                ? CarbonImmutable::parse($filters->from, $timezone)->startOfDay()->utc()
                : null;
            $rangeEnd = $filters->to !== null
                ? CarbonImmutable::parse($filters->to, $timezone)->endOfDay()->utc()
                : null;

            if ($rangeStart !== null && $rangeEnd !== null && $rangeStart->diffInDays($rangeEnd) > self::MAX_DATE_RANGE_DAYS) {
                abort(422, __('payments.date_range_too_large', ['days' => self::MAX_DATE_RANGE_DAYS]));
            }

            if ($rangeStart !== null) {
                $query->where('created_at', '>=', $rangeStart);
            }

            if ($rangeEnd !== null) {
                $query->where('created_at', '<=', $rangeEnd);
            }
        }

        return $query->orderByDesc('created_at');
    }
}
