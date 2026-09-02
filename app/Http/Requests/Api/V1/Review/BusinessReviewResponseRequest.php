<?php

namespace App\Http\Requests\Api\V1\Review;

use Illuminate\Foundation\Http\FormRequest;

class BusinessReviewResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('respond', $this->route('review')) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    public function responseBody(): string
    {
        return trim($this->string('body')->toString());
    }
}
