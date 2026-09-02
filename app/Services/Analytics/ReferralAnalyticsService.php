<?php

namespace App\Services\Analytics;

use App\Domain\Referrals\Enums\ReferralStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Models\Referral;
use App\Models\Reservation;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;

final class ReferralAnalyticsService
{
    /**
     * Business-attributed referral metrics based on referred users' activity at this business.
     *
     * @return array<string, mixed>
     */
    public function metrics(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $referredUserIds = Referral::query()->pluck('referred_user_id');

        if ($referredUserIds->isEmpty()) {
            return [
                'referred_users_with_reservations' => 0,
                'qualified_referrals' => 0,
                'rewarded_referrals' => 0,
                'completed_reservations_by_referred_users' => 0,
                'note' => 'Referrals are platform-scoped; business metrics reflect referred-user activity at this business only.',
            ];
        }

        $completedByReferred = Reservation::query()
            ->where('business_id', $business->id)
            ->where('status', ReservationStatus::Completed->value)
            ->whereIn('customer_id', $referredUserIds)
            ->where('start_at', '<', $range->utcEnd())
            ->where('end_at', '>', $range->utcStart())
            ->count();

        $referredWithReservations = Reservation::query()
            ->where('business_id', $business->id)
            ->whereIn('customer_id', $referredUserIds)
            ->where('start_at', '<', $range->utcEnd())
            ->where('end_at', '>', $range->utcStart())
            ->distinct('customer_id')
            ->count('customer_id');

        return [
            'referred_users_with_reservations' => $referredWithReservations,
            'qualified_referrals' => Referral::query()
                ->whereIn('status', [ReferralStatus::Qualified, ReferralStatus::Rewarded])
                ->whereBetween('qualified_at', [$range->utcStart(), $range->utcEnd()])
                ->count(),
            'rewarded_referrals' => Referral::query()
                ->where('status', ReferralStatus::Rewarded)
                ->whereBetween('rewarded_at', [$range->utcStart(), $range->utcEnd()])
                ->count(),
            'completed_reservations_by_referred_users' => $completedByReferred,
            'note' => 'Referrals are platform-scoped; business metrics reflect referred-user activity at this business only.',
        ];
    }
}
