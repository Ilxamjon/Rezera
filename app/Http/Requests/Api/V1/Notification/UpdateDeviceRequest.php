<?php

namespace App\Http\Requests\Api\V1\Notification;

use App\Domain\Identity\Enums\Locale;
use App\Domain\Notifications\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
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
            'push_token' => ['nullable', 'string', 'max:512'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', Rule::in(Locale::supported())],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
