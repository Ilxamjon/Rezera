<?php

namespace App\Http\Requests\Api\V1\Reservation;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChangeReservationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');

        if (! $reservation instanceof Reservation) {
            return false;
        }

        $status = ReservationStatus::tryFrom((string) $this->input('status'));

        if ($status === null) {
            return Gate::allows('updateStatus', $reservation);
        }

        return Gate::allows('changeStatus', [$reservation, $status]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ReservationStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
