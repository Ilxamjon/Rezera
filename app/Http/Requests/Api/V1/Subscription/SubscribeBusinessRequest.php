<?php

namespace App\Http\Requests\Api\V1\Subscription;

use App\Domain\Subscriptions\Enums\BillingInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscribeBusinessRequest extends FormRequest
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
            'plan_code' => ['required', 'string', 'max:64'],
            'billing_interval' => ['required', 'string', Rule::in(BillingInterval::values())],
        ];
    }
}
