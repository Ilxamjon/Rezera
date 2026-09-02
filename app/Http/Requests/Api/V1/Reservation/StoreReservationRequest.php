<?php

namespace App\Http\Requests\Api\V1\Reservation;

use App\Http\Requests\Api\V1\Concerns\ValidatesReservationInterval;
use App\Models\Business;
use App\Rules\ResourceBelongsToBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    use ValidatesReservationInterval;

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
            ...$this->reservationIntervalRules($business),
            'resource_id' => ['required', 'uuid', new ResourceBelongsToBusiness($business->id)],
            'notes' => ['nullable', 'string', 'max:500'],
            'promo_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        /** @var Business $business */
        $business = $this->route('business');
        $this->withReservationIntervalValidator($validator, $business);
    }

    public function idempotencyKey(): ?string
    {
        $header = $this->header('Idempotency-Key');

        if ($header === null || $header === '') {
            return null;
        }

        return (string) $header;
    }
}
