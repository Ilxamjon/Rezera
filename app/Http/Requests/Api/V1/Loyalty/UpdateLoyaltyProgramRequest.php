<?php

namespace App\Http\Requests\Api\V1\Loyalty;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoyaltyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_enabled' => ['sometimes', 'boolean'],
            'earn_rate_points' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'earn_amount' => ['sometimes', 'integer', 'min:1'],
            'flat_points_per_reservation' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minimum_qualifying_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_points_per_transaction' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'points_expiration_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
