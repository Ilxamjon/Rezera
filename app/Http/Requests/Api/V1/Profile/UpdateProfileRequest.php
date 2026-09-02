<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Domain\Identity\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'locale' => ['sometimes', 'string', Rule::in(Locale::supported())],
            'preferred_language' => ['sometimes', 'string', Rule::in(Locale::supported())],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64', Rule::in(timezone_identifiers_list())],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
        ];
    }
}
