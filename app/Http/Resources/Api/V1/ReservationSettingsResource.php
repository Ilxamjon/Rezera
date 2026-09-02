<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BookingPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BookingPolicy */
class ReservationSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'confirmation_mode' => $this->confirmation_mode->value,
            'auto_confirm_reservations' => $this->confirmation_mode->value === 'instant',
            'min_duration_minutes' => $this->min_duration_minutes,
            'max_duration_minutes' => $this->max_duration_minutes,
            'duration_step_minutes' => $this->duration_step_minutes,
            'min_advance_minutes' => $this->min_advance_minutes,
            'max_advance_days' => $this->max_advance_days,
            'cancellation_deadline_minutes' => $this->cancellation_deadline_minutes,
            'customer_can_cancel' => $this->customer_can_cancel,
            'business_can_cancel' => $this->business_can_cancel,
            'pending_expiry_minutes' => $this->pending_expiry_minutes,
            'check_in_early_minutes' => $this->check_in_early_minutes,
            'no_show_grace_minutes' => $this->no_show_grace_minutes,
            'buffer_minutes' => $this->buffer_minutes,
            'allow_same_day_reservations' => $this->allow_same_day_reservations,
            'max_active_reservations_per_customer' => $this->max_active_reservations_per_customer,
            'max_daily_reservations_per_customer' => $this->max_daily_reservations_per_customer,
            'require_customer_note' => $this->require_customer_note,
            'metadata' => $this->metadata,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
