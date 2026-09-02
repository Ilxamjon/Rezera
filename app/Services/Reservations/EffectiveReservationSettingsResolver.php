<?php

namespace App\Services\Reservations;

use App\Data\Reservations\EffectiveReservationSettings;
use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Models\BookingPolicy;
use App\Models\Business;
use App\Models\Resource;

final class EffectiveReservationSettingsResolver
{
    public function __construct(
        private readonly BusinessSettingsService $businessSettings,
    ) {}

    public function resolve(Business $business, ?Resource $resource = null): EffectiveReservationSettings
    {
        $policy = $this->businessSettings->getForBusiness($business);
        $defaults = $this->businessSettings->defaults();

        return new EffectiveReservationSettings(
            minDurationMinutes: $this->resolveInt($resource?->min_duration_minutes, $policy->min_duration_minutes, $defaults['min_duration_minutes']),
            maxDurationMinutes: $this->resolveInt($resource?->max_duration_minutes, $policy->max_duration_minutes, $defaults['max_duration_minutes']),
            durationStepMinutes: $this->resolveInt($resource?->duration_step_minutes, $policy->duration_step_minutes, $defaults['duration_step_minutes']),
            minAdvanceMinutes: $policy->min_advance_minutes ?? (int) $defaults['min_advance_minutes'],
            maxAdvanceDays: $policy->max_advance_days ?? (int) $defaults['max_advance_days'],
            cancellationDeadlineMinutes: $policy->cancellation_deadline_minutes ?? (int) $defaults['cancellation_deadline_minutes'],
            pendingExpiryMinutes: $policy->pending_expiry_minutes ?? (int) $defaults['pending_expiry_minutes'],
            checkInEarlyMinutes: $policy->check_in_early_minutes ?? (int) $defaults['check_in_early_minutes'],
            noShowGraceMinutes: $policy->no_show_grace_minutes ?? (int) $defaults['no_show_grace_minutes'],
            bufferMinutes: $this->resolveInt($resource?->buffer_minutes, $policy->buffer_minutes, $defaults['buffer_minutes']),
            customerCanCancel: $policy->customer_can_cancel ?? (bool) $defaults['customer_can_cancel'],
            businessCanCancel: $policy->business_can_cancel ?? (bool) $defaults['business_can_cancel'],
            allowSameDayReservations: $policy->allow_same_day_reservations ?? (bool) $defaults['allow_same_day_reservations'],
            requireCustomerNote: $policy->require_customer_note ?? (bool) $defaults['require_customer_note'],
            maxActiveReservationsPerCustomer: $policy->max_active_reservations_per_customer,
            maxDailyReservationsPerCustomer: $policy->max_daily_reservations_per_customer,
            confirmationMode: $policy->confirmation_mode ?? ConfirmationMode::from($defaults['confirmation_mode']),
        );
    }

    private function resolveInt(?int $resourceValue, ?int $businessValue, mixed $default): int
    {
        if ($resourceValue !== null) {
            return $resourceValue;
        }

        if ($businessValue !== null) {
            return $businessValue;
        }

        return (int) $default;
    }
}
