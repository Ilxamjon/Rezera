<?php

namespace App\Http\Requests\Api\V1\Review;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReviewModerationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('moderate', \App\Models\Review::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['publish', 'hide', 'reject', 'restore', 'delete'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function action(): string
    {
        return $this->string('action')->toString();
    }
}
