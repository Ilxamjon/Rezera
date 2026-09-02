<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Domain\Businesses\Enums\BusinessStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateBusinessStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(BusinessStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
