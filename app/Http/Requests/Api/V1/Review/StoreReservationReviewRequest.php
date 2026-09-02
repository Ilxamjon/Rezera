<?php

namespace App\Http\Requests\Api\V1\Review;

use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Reservation $reservation */
        $reservation = $this->route('reservation');
        $business = $reservation->business;

        if ($business === null) {
            return false;
        }

        return $this->user()?->can('create', [Review::class, $business]) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $body = $this->input('body');

            if (is_string($body) && trim($body) === '') {
                $validator->errors()->add('body', __('reviews.body_cannot_be_blank'));
            }
        });
    }

    /**
     * @return array{rating: int, title?: string|null, body?: string|null}
     */
    public function reviewData(): array
    {
        $data = [
            'rating' => (int) $this->input('rating'),
            'title' => $this->filled('title') ? trim($this->string('title')->toString()) : null,
            'body' => $this->filled('body') ? trim($this->string('body')->toString()) : null,
        ];

        if ($data['title'] === '') {
            $data['title'] = null;
        }

        return $data;
    }
}
