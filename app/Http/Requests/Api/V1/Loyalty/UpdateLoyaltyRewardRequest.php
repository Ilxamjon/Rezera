<?php

namespace App\Http\Requests\Api\V1\Loyalty;

use App\Domain\Loyalty\Enums\LoyaltyRewardType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoyaltyRewardRequest extends FormRequest
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
            'points_cost' => ['sometimes', 'integer', 'min:1'],
            'reward_type' => ['sometimes', 'string', Rule::enum(LoyaltyRewardType::class)],
            'value' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'stock' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_redemptions_per_user' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
