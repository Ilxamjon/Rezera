<?php

namespace App\Services\Analytics;

use App\Domain\Analytics\Enums\AnalyticsDatePreset;
use App\Models\Business;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Analytics\AnalyticsMetrics;

final class BusinessAnalyticsService
{
    public function __construct(
        private readonly ReservationAnalyticsService $reservations,
        private readonly RevenueAnalyticsService $revenue,
        private readonly ResourceAnalyticsService $resources,
        private readonly CustomerAnalyticsService $customers,
        private readonly CheckInAnalyticsService $checkIns,
        private readonly LoyaltyAnalyticsService $loyalty,
        private readonly PromotionAnalyticsService $promotions,
        private readonly ReferralAnalyticsService $referrals,
        private readonly ReviewAnalyticsService $reviews,
        private readonly FavoriteAnalyticsService $favorites,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(
        Business $business,
        AnalyticsDateRange $range,
        AnalyticsFilter $filter,
        bool $includeFinancial,
        ?AnalyticsDateRange $compareRange = null,
    ): array {
        $reservationMetrics = $this->reservations->metrics($business, $range, $filter);
        $customerMetrics = $this->customers->metrics($business, $range, $filter);
        $resourceMetrics = $this->resources->summary($business, $range, $filter);

        $loyaltyMetrics = $this->loyalty->metrics($business, $range, $filter);

        $data = [
            'period' => $range->toPeriodArray(),
            'reservations' => $reservationMetrics,
            'customers' => $customerMetrics,
            'occupancy' => [
                'utilization_percent' => $resourceMetrics['utilization_percent'],
                'booked_minutes' => $resourceMetrics['booked_minutes'],
                'available_minutes' => $resourceMetrics['available_minutes'],
            ],
            'loyalty' => [
                'points_earned' => $loyaltyMetrics['points_earned'],
                'points_redeemed' => $loyaltyMetrics['points_redeemed'],
            ],
            'reviews' => $this->reviews->metrics($business, $range),
            'favorites' => $this->favorites->metrics($business, $range),
        ];

        if ($includeFinancial) {
            $revenueMetrics = $this->revenue->metrics($business, $range, $filter);
            $data['revenue'] = $revenueMetrics;
            $data['average_reservation_value'] = $revenueMetrics['average_reservation_value'];
            $data['average_reservation_duration_minutes'] = AnalyticsMetrics::average(
                (int) $resourceMetrics['booked_minutes'],
                (int) $reservationMetrics['total'],
            );
        }

        if ($compareRange !== null) {
            $data['comparison'] = $this->buildComparison($business, $range, $compareRange, $filter, $includeFinancial);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Business $business, AnalyticsFilter $filter, bool $includeFinancial): array
    {
        $today = AnalyticsDateRange::preset($business, AnalyticsDatePreset::Today);
        $thisWeek = AnalyticsDateRange::fromRequest($business, AnalyticsDatePreset::Last7Days->value, null, null);
        $thisMonth = AnalyticsDateRange::preset($business, AnalyticsDatePreset::ThisMonth);

        $build = function (AnalyticsDateRange $range) use ($business, $filter, $includeFinancial): array {
            $payload = [
                'period' => $range->toPeriodArray(),
                'reservations' => $this->reservations->metrics($business, $range, $filter),
                'customers' => [
                    'active_customers' => $this->customers->metrics($business, $range, $filter)['active_customers'],
                    'new_customers' => $this->customers->metrics($business, $range, $filter)['new_customers'],
                ],
                'occupancy' => $this->resources->summary($business, $range, $filter),
            ];

            if ($includeFinancial) {
                $payload['revenue'] = $this->revenue->metrics($business, $range, $filter);
            }

            $payload['loyalty'] = $this->loyalty->metrics($business, $range, $filter);

            return $payload;
        };

        $monthRange = AnalyticsDateRange::preset($business, AnalyticsDatePreset::ThisMonth);
        $previousMonth = $monthRange->previousPeriod();

        return [
            'today' => $build($today),
            'this_week' => $build($thisWeek),
            'this_month' => $build($thisMonth),
            'comparison' => $this->buildComparison(
                $business,
                $monthRange,
                $previousMonth,
                $filter,
                $includeFinancial,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildComparison(
        Business $business,
        AnalyticsDateRange $current,
        AnalyticsDateRange $previous,
        AnalyticsFilter $filter,
        bool $includeFinancial,
    ): array {
        $currentReservations = $this->reservations->metrics($business, $current, $filter);
        $previousReservations = $this->reservations->metrics($business, $previous, $filter);

        $comparison = [
            'period' => [
                'current' => $current->toPeriodArray(),
                'previous' => $previous->toPeriodArray(),
            ],
            'reservations_total' => AnalyticsMetrics::compare(
                $currentReservations['total'],
                $previousReservations['total'],
            ),
            'completed_reservations' => AnalyticsMetrics::compare(
                $currentReservations['completed'],
                $previousReservations['completed'],
            ),
        ];

        if ($includeFinancial) {
            $currentRevenue = $this->revenue->metrics($business, $current, $filter);
            $previousRevenue = $this->revenue->metrics($business, $previous, $filter);

            $comparison['net_reservation_value'] = AnalyticsMetrics::compare(
                $currentRevenue['net_reservation_value'],
                $previousRevenue['net_reservation_value'],
            );
            $comparison['amount_paid'] = AnalyticsMetrics::compare(
                $currentRevenue['amount_paid'],
                $previousRevenue['amount_paid'],
            );
        }

        return $comparison;
    }
}
