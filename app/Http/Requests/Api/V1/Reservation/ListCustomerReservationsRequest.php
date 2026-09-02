<?php

namespace App\Http\Requests\Api\V1\Reservation;

use App\Domain\Reservations\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCustomerReservationsRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'business_id' => ['nullable', 'uuid', 'exists:businesses,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'scope' => ['nullable', Rule::in(['upcoming', 'past'])],
            'sort' => ['nullable', Rule::in(['start_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
