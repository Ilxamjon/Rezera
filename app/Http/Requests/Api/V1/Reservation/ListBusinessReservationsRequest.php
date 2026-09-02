<?php

namespace App\Http\Requests\Api\V1\Reservation;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListBusinessReservationsRequest extends FormRequest
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
        /** @var Business $business */
        $business = $this->route('business');

        return [
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'resource_id' => [
                'nullable',
                'uuid',
                Rule::exists('resources', 'id')->where(fn ($query) => $query->where('business_id', $business->id)),
            ],
            'date' => ['nullable', 'regex:/^(\d{4}-\d{2}-\d{2}|today)$/'],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', Rule::in(['today', 'upcoming', 'past'])],
            'sort' => ['nullable', Rule::in(['start_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'reservation_number' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('reservation_number') && ! $this->filled('search')) {
            $this->merge(['search' => $this->string('reservation_number')->toString()]);
        }
    }
}
