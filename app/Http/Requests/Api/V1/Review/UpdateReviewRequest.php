<?php

namespace App\Http\Requests\Api\V1\Review;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('review')) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'body' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reviewData(): array
    {
        $data = $this->validated();

        if (array_key_exists('title', $data) && is_string($data['title'])) {
            $data['title'] = trim($data['title']) ?: null;
        }

        if (array_key_exists('body', $data) && is_string($data['body'])) {
            $data['body'] = trim($data['body']) ?: null;
        }

        return $data;
    }
}
