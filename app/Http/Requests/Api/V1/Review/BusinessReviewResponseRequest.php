<?php

namespace App\Http\Requests\Api\V1\Review;

use App\Models\Business;
use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

class BusinessReviewResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('review');
        $business = $this->route('business');

        if (! $review instanceof Review || ! $business instanceof Business) {
            return false;
        }

        if ($review->business_id !== $business->id) {
            abort(404);
        }

        return $this->user()?->can('respond', $review) === true;
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
