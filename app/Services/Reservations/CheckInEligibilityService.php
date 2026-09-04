<?php

namespace App\Services\Reservations;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class CheckInEligibilityService
{
    public function __construct(
        private readonly EffectiveReservationSettingsResolver $settingsResolver,
    ) {}

    /**
     * @return array{eligible: bool, reason: ?string}
     */
    public function evaluate(Reservation $reservation, ?CarbonImmutable $now = null): array
    {
        $reservation->loadMissing(['business', 'resource']);

        if (in_array($reservation->status, [
            ReservationStatus::Cancelled,
            ReservationStatus::Rejected,
            ReservationStatus::Expired,
        ], true)) {
            return ['eligible' => false, 'reason' => 'cancelled'];
        }

        if (in_array($reservation->status, [
            ReservationStatus::Completed,
            ReservationStatus::NoShow,
        ], true)) {
            return ['eligible' => false, 'reason' => 'completed'];
        }

        if ($reservation->status === ReservationStatus::CheckedIn) {
            return ['eligible' => false, 'reason' => 'already_checked_in'];
        }

        if ($reservation->status !== ReservationStatus::Confirmed) {
            return ['eligible' => false, 'reason' => 'invalid_status'];
        }

        if ($reservation->resource === null || ! $reservation->resource->status?->isBookable()) {
            return ['eligible' => false, 'reason' => 'resource_not_bookable'];
        }

        $timezone = $reservation->business?->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $now ??= CarbonImmutable::now($timezone);
        $start = CarbonImmutable::instance($reservation->start_at)->timezone($timezone);
        $end = CarbonImmutable::instance($reservation->end_at)->timezone($timezone);

        $settings = $this->settingsResolver->resolve($reservation->business, $reservation->resource);
        $earlyMinutes = $settings->checkInEarlyMinutes;
        $lateMinutes = $settings->noShowGraceMinutes;

        $windowStart = $start->subMinutes($earlyMinutes);
        $windowEnd = $end->addMinutes($lateMinutes);

        if ($now->lessThan($windowStart)) {
            return ['eligible' => false, 'reason' => 'too_early'];
        }

        if ($now->greaterThan($windowEnd)) {
            return ['eligible' => false, 'reason' => 'too_late'];
        }

        return ['eligible' => true, 'reason' => null];
    }

    public function assertEligible(Reservation $reservation): void
    {
        $result = $this->evaluate($reservation);

        if (! $result['eligible']) {
            throw ValidationException::withMessages([
                'reservation' => [__('reservations.check_in.'.$result['reason'])],
            ]);
        }
    }
}
