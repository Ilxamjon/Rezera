<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateBusinessVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(BusinessVerificationStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
