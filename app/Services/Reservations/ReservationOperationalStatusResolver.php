<?php

namespace App\Services\Reservations;

use App\Domain\Reservations\Enums\ReservationOperationalStatus;
use App\Domain\Reservations\Enums\ReservationSessionStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use Carbon\CarbonImmutable;

final class ReservationOperationalStatusResolver
{
    public function __construct(
        private readonly CheckInEligibilityService $eligibility,
    ) {}

    public function resolve(Reservation $reservation, ?CarbonImmutable $now = null): ReservationOperationalStatus
    {
        if (in_array($reservation->status, [
            ReservationStatus::Cancelled,
            ReservationStatus::Rejected,
        ], true)) {
            return ReservationOperationalStatus::Cancelled;
        }

        if ($reservation->status === ReservationStatus::Expired) {
            return ReservationOperationalStatus::Expired;
        }

        if ($reservation->status === ReservationStatus::Completed
            || $reservation->checked_out_at !== null
            || $reservation->activeSession?->status === ReservationSessionStatus::Completed) {
            return ReservationOperationalStatus::CheckedOut;
        }

        if ($reservation->status === ReservationStatus::CheckedIn
            || $reservation->checked_in_at !== null
            || $reservation->activeSession?->status === ReservationSessionStatus::Active) {
            return ReservationOperationalStatus::CheckedIn;
        }

        $evaluation = $this->eligibility->evaluate($reservation, $now);

        if ($evaluation['eligible']) {
            return ReservationOperationalStatus::EligibleForCheckIn;
        }

        if (in_array($evaluation['reason'], ['too_late', 'completed'], true)) {
            $timezone = $reservation->business?->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
            $now ??= CarbonImmutable::now($timezone);
            $end = CarbonImmutable::instance($reservation->end_at)->timezone($timezone);

            if ($now->greaterThan($end) && $reservation->status === ReservationStatus::Confirmed) {
                return ReservationOperationalStatus::Expired;
            }
        }

        return ReservationOperationalStatus::Scheduled;
    }
}
