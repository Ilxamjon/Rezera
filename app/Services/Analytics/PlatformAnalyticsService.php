<?php

namespace App\Services\Analytics;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Models\BusinessFavorite;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SavedSearch;
use App\Models\SavedSearchAlert;
use App\Models\User;
use Carbon\CarbonImmutable;

final class PlatformAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(?string $from = null, ?string $to = null): array
    {
        $fromUtc = $from !== null
            ? CarbonImmutable::parse($from, 'UTC')->startOfDay()
            : CarbonImmutable::now('UTC')->subDays(29)->startOfDay();
        $toUtc = $to !== null
            ? CarbonImmutable::parse($to, 'UTC')->endOfDay()
            : CarbonImmutable::now('UTC')->endOfDay();

        $reservationCounts = Reservation::query()
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $paymentVolume = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$fromUtc, $toUtc])
            ->sum('amount');

        return [
            'period' => [
                'from' => $fromUtc->toDateString(),
                'to' => $toUtc->toDateString(),
                'timezone' => 'UTC',
            ],
            'users' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('status', UserStatus::Active)->count(),
            ],
            'businesses' => [
                'total' => Business::query()->count(),
                'approved' => Business::query()->where('status', BusinessStatus::Approved)->count(),
            ],
            'favorites' => [
                'total_relationships' => BusinessFavorite::query()->count(),
                'added_in_period' => BusinessFavorite::query()
                    ->whereBetween('created_at', [$fromUtc, $toUtc])
                    ->count(),
            ],
            'saved_searches' => [
                'total' => SavedSearch::query()->count(),
                'alerts_enabled' => SavedSearch::query()->where('alert_enabled', true)->count(),
                'alerts_sent_in_period' => SavedSearchAlert::query()
                    ->whereBetween('created_at', [$fromUtc, $toUtc])
                    ->where('status', 'sent')
                    ->count(),
            ],
            'reservations' => [
                'total' => (int) $reservationCounts->sum(),
                'completed' => (int) ($reservationCounts[ReservationStatus::Completed->value] ?? 0),
                'cancelled' => (int) ($reservationCounts[ReservationStatus::Cancelled->value] ?? 0),
            ],
            'revenue' => [
                'amount_paid' => (int) $paymentVolume,
            ],
        ];
    }
}
