<?php

namespace App\Services\Analytics;

use App\Domain\Reservations\Enums\PaymentStatus as ReservationPaymentStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Models\Payment;
use App\Services\Analytics\Concerns\BuildsReservationAnalyticsQueries;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Analytics\AnalyticsMetrics;
use Illuminate\Database\Eloquent\Builder;

final class RevenueAnalyticsService
{
    use BuildsReservationAnalyticsQueries;

    /**
     * @return array<string, mixed>
     */
    public function metrics(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $reservationAgg = $this->reservationQuery($business, $range, $filter)
            ->whereIn('status', [
                ReservationStatus::Completed->value,
                ReservationStatus::CheckedIn->value,
                ReservationStatus::Confirmed->value,
                ReservationStatus::Pending->value,
                ReservationStatus::NoShow->value,
            ])
            ->selectRaw('coalesce(sum(subtotal_amount), 0) as gross')
            ->selectRaw('coalesce(sum(discount_amount), 0) as discounts')
            ->selectRaw('coalesce(sum(total_amount), 0) as net')
            ->selectRaw('count(*) as reservation_count')
            ->first();

        $paymentQuery = $this->paymentQuery($business, $range, $filter);

        $paymentAgg = (clone $paymentQuery)
            ->selectRaw("coalesce(sum(case when status = 'paid' then amount else 0 end), 0) as paid")
            ->selectRaw("coalesce(sum(case when status in ('refunded', 'partially_refunded') then amount else 0 end), 0) as refunded")
            ->selectRaw("sum(case when status = 'paid' then 1 else 0 end) as successful_payments")
            ->selectRaw("sum(case when status = 'failed' then 1 else 0 end) as failed_payments")
            ->selectRaw("sum(case when status in ('pending', 'processing') then 1 else 0 end) as pending_payments")
            ->first();

        $statusBreakdown = (clone $paymentQuery)
            ->selectRaw('status, count(*) as total, coalesce(sum(amount), 0) as amount')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($row): array {
                $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;

                return [
                    $status => [
                        'count' => (int) $row->total,
                        'amount' => (int) $row->amount,
                    ],
                ];
            })
            ->all();

        $net = (int) ($reservationAgg->net ?? 0);
        $paid = (int) ($paymentAgg->paid ?? 0);
        $reservationCount = (int) ($reservationAgg->reservation_count ?? 0);

        $outstanding = $this->reservationQuery($business, $range, $filter)
            ->whereIn('payment_status', [
                ReservationPaymentStatus::Unpaid->value,
                ReservationPaymentStatus::PayAtVenue->value,
            ])
            ->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::CheckedIn->value,
                ReservationStatus::Completed->value,
            ])
            ->selectRaw('coalesce(sum(total_amount), 0) as outstanding')
            ->value('outstanding');

        return [
            'currency' => $business->currency ?? config('rezera.default_currency', 'UZS'),
            'gross_reservation_value' => (int) ($reservationAgg->gross ?? 0),
            'discounts' => (int) ($reservationAgg->discounts ?? 0),
            'net_reservation_value' => $net,
            'amount_paid' => $paid,
            'amount_refunded' => (int) ($paymentAgg->refunded ?? 0),
            'estimated_outstanding' => (int) $outstanding,
            'successful_payments' => (int) ($paymentAgg->successful_payments ?? 0),
            'failed_payments' => (int) ($paymentAgg->failed_payments ?? 0),
            'pending_payments' => (int) ($paymentAgg->pending_payments ?? 0),
            'average_transaction_value' => AnalyticsMetrics::average($paid, (int) ($paymentAgg->successful_payments ?? 0)),
            'average_reservation_value' => AnalyticsMetrics::average($net, $reservationCount),
            'payment_status_breakdown' => $statusBreakdown,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trends(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $trunc = $filter->granularity->sqlTrunc();
        $timezone = $range->timezone;

        $reservationRows = $this->reservationQuery($business, $range, $filter)
            ->selectRaw(
                "date_trunc(?, (start_at AT TIME ZONE 'UTC') AT TIME ZONE ?, 'UTC')::date as bucket",
                [$trunc, $timezone],
            )
            ->selectRaw('coalesce(sum(subtotal_amount), 0) as gross')
            ->selectRaw('coalesce(sum(discount_amount), 0) as discount')
            ->selectRaw('coalesce(sum(total_amount), 0) as net')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy(fn ($row) => (string) $row->bucket);

        $paymentRows = $this->paymentQuery($business, $range, $filter)
            ->selectRaw(
                "date_trunc(?, (coalesce(paid_at, created_at) AT TIME ZONE 'UTC') AT TIME ZONE ?, 'UTC')::date as bucket",
                [$trunc, $timezone],
            )
            ->selectRaw("coalesce(sum(case when status = 'paid' then amount else 0 end), 0) as paid")
            ->selectRaw("coalesce(sum(case when status in ('refunded', 'partially_refunded') then amount else 0 end), 0) as refunded")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy(fn ($row) => (string) $row->bucket);

        $buckets = $reservationRows->keys()->merge($paymentRows->keys())->unique()->sort()->values();

        return [
            'granularity' => $filter->granularity->value,
            'currency' => $business->currency ?? config('rezera.default_currency', 'UZS'),
            'data' => $buckets->map(function (string $bucket) use ($reservationRows, $paymentRows) {
                $reservation = $reservationRows->get($bucket);
                $payment = $paymentRows->get($bucket);

                return [
                    'date' => $bucket,
                    'gross' => (int) ($reservation->gross ?? 0),
                    'discount' => (int) ($reservation->discount ?? 0),
                    'net' => (int) ($reservation->net ?? 0),
                    'paid' => (int) ($payment->paid ?? 0),
                    'refunded' => (int) ($payment->refunded ?? 0),
                ];
            })->values()->all(),
        ];
    }

    protected function paymentQuery(
        Business $business,
        AnalyticsDateRange $range,
        AnalyticsFilter $filter,
    ): Builder {
        $query = Payment::query()
            ->where('business_id', $business->id)
            ->where(function (Builder $builder) use ($range): void {
                $builder
                    ->whereBetween('paid_at', [$range->utcStart(), $range->utcEnd()])
                    ->orWhere(function (Builder $nested) use ($range): void {
                        $nested
                            ->whereNull('paid_at')
                            ->whereBetween('created_at', [$range->utcStart(), $range->utcEnd()]);
                    });
            });

        if ($filter->paymentStatus !== null) {
            $query->where('status', $filter->paymentStatus);
        }

        if ($filter->customerId !== null) {
            $query->where('user_id', $filter->customerId);
        }

        if ($filter->resourceId !== null || $filter->resourceCategoryId !== null) {
            $query->whereHas('reservation', function (Builder $builder) use ($filter): void {
                if ($filter->resourceId !== null) {
                    $builder->where('resource_id', $filter->resourceId);
                }
                if ($filter->resourceCategoryId !== null) {
                    $builder->whereHas('resource', fn (Builder $resource) => $resource
                        ->where('resource_group_id', $filter->resourceCategoryId));
                }
            });
        }

        return $query;
    }
}
