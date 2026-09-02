<?php

namespace App\Http\Requests\Api\V1\Loyalty;

use App\Domain\Loyalty\Enums\LoyaltyRewardType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoyaltyRewardRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'reward_type' => ['required', 'string', Rule::enum(LoyaltyRewardType::class)],
            'value' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'stock' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_redemptions_per_user' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
        ];
    }
}
