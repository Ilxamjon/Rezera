<?php

namespace App\Http\Requests\Api\V1\Notification;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
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
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.notification_type' => ['required', Rule::enum(NotificationType::class)],
            'preferences.*.channel' => ['required', Rule::enum(NotificationChannel::class)],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }
}
