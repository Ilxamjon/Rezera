<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Domain\Identity\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(UserStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
