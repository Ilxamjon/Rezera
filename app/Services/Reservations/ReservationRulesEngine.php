<?php

namespace App\Services\Reservations;

use App\Data\Reservations\EffectiveReservationSettings;
use App\Domain\Availability\Enums\AvailabilityReason;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Exceptions\Reservations\ReservationRuleException;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use App\Support\Time\TimeInterval;
use Carbon\CarbonImmutable;

final class ReservationRulesEngine
{
    public function __construct(
        private readonly EffectiveReservationSettingsResolver $settingsResolver,
    ) {}

    public function getEffectiveSettings(Business $business, ?Resource $resource = null): EffectiveReservationSettings
    {
        return $this->settingsResolver->resolve($business, $resource);
    }

    /**
     * @param  array{notes?: ?string}  $data
     */
    public function validateCreation(
        Business $business,
        Resource $resource,
        User $customer,
        TimeInterval $interval,
        string $timezone,
        array $data = [],
        bool $allowPast = false,
    ): EffectiveReservationSettings {
        $settings = $this->getEffectiveSettings($business, $resource);
        $durationMinutes = (int) $interval->start->diffInMinutes($interval->end);

        $this->validateDuration($settings, $durationMinutes);
        $this->validateStartTime($settings, $interval->start, $timezone);
        $this->validateAdvanceBooking($settings, $interval->start, $timezone, $allowPast);

        if ($settings->requireCustomerNote && empty(trim((string) ($data['notes'] ?? '')))) {
            throw ReservationRuleException::noteRequired();
        }

        return $settings;
    }

    public function validateDuration(EffectiveReservationSettings $settings, int $durationMinutes): void
    {
        if ($durationMinutes < $settings->minDurationMinutes) {
            throw ReservationRuleException::durationTooShort($settings->minDurationMinutes);
        }

        if ($durationMinutes > $settings->maxDurationMinutes) {
            throw ReservationRuleException::durationTooLong($settings->maxDurationMinutes);
        }

        if ($durationMinutes % $settings->durationStepMinutes !== 0) {
            throw ReservationRuleException::invalidStep($settings->durationStepMinutes);
        }
    }

    public function validateStartTime(
        EffectiveReservationSettings $settings,
        CarbonImmutable $start,
        string $timezone,
    ): void {
        $localStart = $start->setTimezone($timezone);
        $minutesFromMidnight = ($localStart->hour * 60) + $localStart->minute;

        if ($minutesFromMidnight % $settings->durationStepMinutes !== 0) {
            throw ReservationRuleException::invalidStartTime();
        }
    }

    public function validateAdvanceBooking(
        EffectiveReservationSettings $settings,
        CarbonImmutable $start,
        string $timezone,
        bool $allowPast = false,
    ): void {
        $now = CarbonImmutable::now($timezone);
        $localStart = $start->setTimezone($timezone);

        if (! $allowPast && $localStart->lessThanOrEqualTo($now)) {
            throw ReservationRuleException::inPast();
        }

        $today = $now->startOfDay();
        $reservationDay = $localStart->startOfDay();

        if (! $settings->allowSameDayReservations && $reservationDay->equalTo($today)) {
            throw ReservationRuleException::sameDayDisabled();
        }

        $minutesUntilStart = (int) $now->diffInMinutes($localStart, false);

        if ($minutesUntilStart < $settings->minAdvanceMinutes) {
            throw ReservationRuleException::tooSoon($settings->minAdvanceMinutes);
        }

        $maxDate = $today->addDays($settings->maxAdvanceDays);

        if ($reservationDay->greaterThan($maxDate)) {
            throw ReservationRuleException::tooFar($settings->maxAdvanceDays);
        }
    }

    public function validateCustomerLimits(
        Business $business,
        User $customer,
        EffectiveReservationSettings $settings,
        CarbonImmutable $start,
        string $timezone,
        ?string $ignoreReservationId = null,
    ): void {
        if ($settings->maxActiveReservationsPerCustomer !== null) {
            $activeCount = $this->countActiveReservations($business, $customer, $ignoreReservationId);

            if ($activeCount >= $settings->maxActiveReservationsPerCustomer) {
                throw ReservationRuleException::customerLimitReached('active', $settings->maxActiveReservationsPerCustomer);
            }
        }

        if ($settings->maxDailyReservationsPerCustomer !== null) {
            $localStart = $start->setTimezone($timezone);
            $dayStart = $localStart->startOfDay()->utc();
            $dayEnd = $localStart->endOfDay()->utc();

            $dailyCount = Reservation::query()
                ->where('business_id', $business->id)
                ->where('customer_id', $customer->id)
                ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
                ->where('start_at', '>=', $dayStart)
                ->where('start_at', '<=', $dayEnd)
                ->when($ignoreReservationId !== null, fn ($q) => $q->where('id', '!=', $ignoreReservationId))
                ->count();

            if ($dailyCount >= $settings->maxDailyReservationsPerCustomer) {
                throw ReservationRuleException::customerLimitReached('daily', $settings->maxDailyReservationsPerCustomer);
            }
        }
    }

    public function canCustomerCancel(Reservation $reservation): bool
    {
        $settings = $this->getEffectiveSettings($reservation->business, $reservation->resource);

        if (! $settings->customerCanCancel) {
            return false;
        }

        $timezone = $reservation->business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $deadline = $this->getCancellationDeadline($reservation, $settings, $timezone);

        return CarbonImmutable::now($timezone)->lessThan($deadline);
    }

    public function canBusinessCancel(Reservation $reservation): bool
    {
        return $this->getEffectiveSettings($reservation->business, $reservation->resource)->businessCanCancel;
    }

    public function assertCustomerCanCancel(Reservation $reservation): void
    {
        $settings = $this->getEffectiveSettings($reservation->business, $reservation->resource);

        if (! $settings->customerCanCancel) {
            throw ReservationRuleException::customerCancellationNotAllowed();
        }

        $timezone = $reservation->business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');

        if (CarbonImmutable::now($timezone)->greaterThanOrEqualTo($this->getCancellationDeadline($reservation, $settings, $timezone))) {
            throw ReservationRuleException::cancellationDeadlinePassed();
        }
    }

    public function assertBusinessCanCancel(Reservation $reservation): void
    {
        if (! $this->canBusinessCancel($reservation)) {
            throw ReservationRuleException::businessCancellationNotAllowed();
        }
    }

    public function getCancellationDeadline(
        Reservation $reservation,
        ?EffectiveReservationSettings $settings = null,
        ?string $timezone = null,
    ): CarbonImmutable {
        $settings ??= $this->getEffectiveSettings($reservation->business, $reservation->resource);
        $timezone ??= $reservation->business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');

        return CarbonImmutable::instance($reservation->start_at)
            ->setTimezone($timezone)
            ->subMinutes($settings->cancellationDeadlineMinutes);
    }

    public function countActiveReservations(Business $business, User $customer, ?string $ignoreReservationId = null): int
    {
        return Reservation::query()
            ->where('business_id', $business->id)
            ->where('customer_id', $customer->id)
            ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
            ->where('start_at', '>', now())
            ->when($ignoreReservationId !== null, fn ($q) => $q->where('id', '!=', $ignoreReservationId))
            ->lockForUpdate()
            ->count();
    }

    public function matchAvailabilityViolation(
        Business $business,
        TimeInterval $interval,
        string $timezone,
        ?Resource $resource = null,
    ): ?AvailabilityReason {
        try {
            $settings = $this->getEffectiveSettings($business, $resource);
            $durationMinutes = (int) $interval->start->diffInMinutes($interval->end);

            $this->validateDuration($settings, $durationMinutes);
            $this->validateStartTime($settings, $interval->start, $timezone);
            $this->validateAdvanceBooking($settings, $interval->start, $timezone);

            return null;
        } catch (ReservationRuleException $exception) {
            return $this->mapToAvailabilityReason($exception);
        }
    }

    public function matchAdvanceAvailabilityViolation(
        Business $business,
        TimeInterval $interval,
        string $timezone,
    ): ?AvailabilityReason {
        try {
            $settings = $this->getEffectiveSettings($business);
            $this->validateAdvanceBooking($settings, $interval->start, $timezone);

            return null;
        } catch (ReservationRuleException $exception) {
            return $this->mapToAvailabilityReason($exception);
        }
    }

    public function matchDurationAvailabilityViolation(
        Business $business,
        TimeInterval $interval,
        string $timezone,
        Resource $resource,
    ): ?AvailabilityReason {
        try {
            $settings = $this->getEffectiveSettings($business, $resource);
            $durationMinutes = (int) $interval->start->diffInMinutes($interval->end);

            $this->validateDuration($settings, $durationMinutes);
            $this->validateStartTime($settings, $interval->start, $timezone);

            return null;
        } catch (ReservationRuleException $exception) {
            return $this->mapToAvailabilityReason($exception);
        }
    }

    private function mapToAvailabilityReason(ReservationRuleException $exception): AvailabilityReason
    {
        return match ($exception->errorCode) {
            'reservation_duration_too_short',
            'reservation_duration_too_long',
            'invalid_reservation_step' => AvailabilityReason::InvalidDuration,
            'reservation_start_time_invalid' => AvailabilityReason::InvalidStartTime,
            'reservation_too_soon' => AvailabilityReason::AdvanceTooSoon,
            'reservation_too_far_in_future' => AvailabilityReason::AdvanceTooFar,
            'same_day_reservations_disabled' => AvailabilityReason::SameDayDisabled,
            'reservation_in_past' => AvailabilityReason::Past,
            default => AvailabilityReason::Blocked,
        };
    }
}

