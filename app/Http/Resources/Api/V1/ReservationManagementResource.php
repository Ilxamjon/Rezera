<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Reservation
 */
class ReservationManagementResource extends ReservationResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->customer_name_snapshot ?? $this->customer?->name,
                'phone' => $this->customer_phone_snapshot ?? $this->customer?->phone,
            ],
            'customer_name_snapshot' => $this->customer_name_snapshot,
            'customer_phone_snapshot' => $this->customer_phone_snapshot,
            'confirmation_mode' => $this->confirmation_mode?->value,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            'check_in_method' => $this->check_in_method?->value,
            'check_out_method' => $this->check_out_method?->value,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'no_show_at' => $this->no_show_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}
