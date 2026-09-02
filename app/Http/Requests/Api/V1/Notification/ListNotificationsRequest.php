<?php

namespace App\Http\Requests\Api\V1\Notification;

use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
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
            'unread' => ['nullable', 'boolean'],
            'type' => ['nullable', Rule::enum(NotificationType::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
