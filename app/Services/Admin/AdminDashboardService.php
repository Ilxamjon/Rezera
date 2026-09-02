<?php

namespace App\Services\Admin;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AdminDashboardService
{
    public function summary(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = $dateFrom !== null
            ? CarbonImmutable::parse($dateFrom, 'UTC')->startOfDay()
            : CarbonImmutable::now('UTC')->startOfDay();
        $to = $dateTo !== null
            ? CarbonImmutable::parse($dateTo, 'UTC')->endOfDay()
            : CarbonImmutable::now('UTC')->endOfDay();

        $todayStart = CarbonImmutable::now('UTC')->startOfDay();
        $todayEnd = CarbonImmutable::now('UTC')->endOfDay();
        $weekStart = CarbonImmutable::now('UTC')->startOfWeek();
        $monthStart = CarbonImmutable::now('UTC')->startOfMonth();

        $reservationCounts = Reservation::query()
            ->selectRaw('status, count(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status')
            ->pluck('total', 'status');

        $paymentCounts = Payment::query()
            ->selectRaw('status, count(*) as total, coalesce(sum(amount), 0) as volume')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($row) => $row->status);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'timezone' => 'UTC',
            ],
            'users' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('status', UserStatus::Active)->count(),
            ],
            'businesses' => [
                'total' => Business::query()->count(),
                'approved' => Business::query()->where('status', BusinessStatus::Approved)->count(),
                'pending_review' => Business::query()->where('status', BusinessStatus::PendingReview)->count(),
                'suspended' => Business::query()->where('status', BusinessStatus::Suspended)->count(),
            ],
            'resources' => [
                'total' => Resource::query()->count(),
            ],
            'reservations' => [
                'today' => Reservation::query()->whereBetween('created_at', [$todayStart, $todayEnd])->count(),
                'this_week' => Reservation::query()->where('created_at', '>=', $weekStart)->count(),
                'this_month' => Reservation::query()->where('created_at', '>=', $monthStart)->count(),
                'confirmed' => (int) ($reservationCounts[ReservationStatus::Confirmed->value] ?? 0),
                'cancelled' => (int) ($reservationCounts[ReservationStatus::Cancelled->value] ?? 0),
                'in_period' => (int) $reservationCounts->sum(),
            ],
            'payments' => [
                'volume' => (int) ($paymentCounts[PaymentStatus::Paid->value]->volume ?? 0),
                'successful' => (int) ($paymentCounts[PaymentStatus::Paid->value]->total ?? 0),
                'failed' => (int) ($paymentCounts[PaymentStatus::Failed->value]->total ?? 0),
            ],
        ];
    }
}
