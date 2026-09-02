<?php

namespace App\Http\Requests\Api\V1\Referrals;

use Illuminate\Foundation\Http\FormRequest;

class ValidateReferralCodeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:16'],
        ];
    }
}
