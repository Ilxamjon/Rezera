<?php

namespace App\Http\Requests\Api\V1\Loyalty;

use Illuminate\Foundation\Http\FormRequest;

class AdjustLoyaltyPointsRequest extends FormRequest
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
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
