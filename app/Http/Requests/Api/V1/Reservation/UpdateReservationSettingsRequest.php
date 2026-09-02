<?php

namespace App\Http\Requests\Api\V1\Reservation;

use App\Domain\Reservations\Enums\ConfirmationMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateReservationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmation_mode' => ['sometimes', Rule::enum(ConfirmationMode::class)],
            'min_duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'max_duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'duration_step_minutes' => ['sometimes', 'integer', 'min:5', 'max:240'],
            'min_advance_minutes' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'max_advance_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'cancellation_deadline_minutes' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'customer_can_cancel' => ['sometimes', 'boolean'],
            'business_can_cancel' => ['sometimes', 'boolean'],
            'pending_expiry_minutes' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'check_in_early_minutes' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'no_show_grace_minutes' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'buffer_minutes' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'allow_same_day_reservations' => ['sometimes', 'boolean'],
            'max_active_reservations_per_customer' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'max_daily_reservations_per_customer' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'require_customer_note' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $min = $this->input('min_duration_minutes');
            $max = $this->input('max_duration_minutes');
            $step = $this->input('duration_step_minutes');

            if ($min !== null && $max !== null && (int) $min > (int) $max) {
                $validator->errors()->add('min_duration_minutes', __('reservation_rules.settings_min_exceeds_max'));
            }

            if ($min !== null && $step !== null && (int) $min % (int) $step !== 0) {
                $validator->errors()->add('duration_step_minutes', __('reservation_rules.settings_step_incompatible'));
            }

            if ($max !== null && $step !== null && (int) $max % (int) $step !== 0) {
                $validator->errors()->add('duration_step_minutes', __('reservation_rules.settings_step_incompatible'));
            }
        });
    }
}
