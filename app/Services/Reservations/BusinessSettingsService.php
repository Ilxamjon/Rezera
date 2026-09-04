<?php

namespace App\Services\Reservations;

use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Models\BookingPolicy;
use App\Models\Business;
use Illuminate\Validation\ValidationException;

final class BusinessSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return config('reservation_rules.defaults', []);
    }

    public function getForBusiness(Business $business): BookingPolicy
    {
        return $this->getOrCreateForBusiness($business);
    }

    public function getOrCreateForBusiness(Business $business): BookingPolicy
    {
        $existing = BookingPolicy::query()
            ->where('business_id', $business->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return BookingPolicy::query()->create([
            'business_id' => $business->id,
            ...$this->defaults(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Business $business, array $attributes): BookingPolicy
    {
        $this->validateSettings($attributes);

        $policy = $this->getOrCreateForBusiness($business);
        $policy->update($attributes);

        return $policy->fresh();
    }

    public function resetToDefaults(Business $business): BookingPolicy
    {
        return $this->update($business, $this->defaults());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function validateSettings(array $attributes): void
    {
        $min = $attributes['min_duration_minutes'] ?? null;
        $max = $attributes['max_duration_minutes'] ?? null;
        $step = $attributes['duration_step_minutes'] ?? null;

        if ($min !== null && $max !== null && (int) $min > (int) $max) {
            throw ValidationException::withMessages([
                'min_duration_minutes' => [__('reservation_rules.settings_min_exceeds_max')],
            ]);
        }

        if ($min !== null && $step !== null && (int) $min % (int) $step !== 0) {
            throw ValidationException::withMessages([
                'duration_step_minutes' => [__('reservation_rules.settings_step_incompatible')],
            ]);
        }

        if ($max !== null && $step !== null && (int) $max % (int) $step !== 0) {
            throw ValidationException::withMessages([
                'duration_step_minutes' => [__('reservation_rules.settings_step_incompatible')],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(BookingPolicy $policy): array
    {
        return [
            'confirmation_mode' => $policy->confirmation_mode->value,
            'auto_confirm_reservations' => $policy->confirmation_mode === ConfirmationMode::Instant,
            'min_duration_minutes' => $policy->min_duration_minutes,
            'max_duration_minutes' => $policy->max_duration_minutes,
            'duration_step_minutes' => $policy->duration_step_minutes,
            'min_advance_minutes' => $policy->min_advance_minutes,
            'max_advance_days' => $policy->max_advance_days,
            'cancellation_deadline_minutes' => $policy->cancellation_deadline_minutes,
            'customer_can_cancel' => $policy->customer_can_cancel,
            'business_can_cancel' => $policy->business_can_cancel,
            'pending_expiry_minutes' => $policy->pending_expiry_minutes,
            'check_in_early_minutes' => $policy->check_in_early_minutes,
            'no_show_grace_minutes' => $policy->no_show_grace_minutes,
            'buffer_minutes' => $policy->buffer_minutes,
            'allow_same_day_reservations' => $policy->allow_same_day_reservations,
            'max_active_reservations_per_customer' => $policy->max_active_reservations_per_customer,
            'max_daily_reservations_per_customer' => $policy->max_daily_reservations_per_customer,
            'require_customer_note' => $policy->require_customer_note,
            'metadata' => $policy->metadata,
        ];
    }
}
