<?php

namespace App\Http\Requests\Api\V1\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class CancelBusinessSubscriptionRequest extends FormRequest
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
            'at_period_end' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
