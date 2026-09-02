<?php

namespace App\Http\Requests\Api\V1\Promo;

use App\Http\Requests\Api\V1\Concerns\ValidatesReservationInterval;
use App\Models\Business;
use App\Models\PromoCode;
use App\Rules\ResourceBelongsToBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidatePromoCodeRequest extends FormRequest
{
    use ValidatesReservationInterval;

    public function authorize(): bool
    {
        /** @var Business $business */
        $business = $this->route('business');

        return $this->user()?->can('validate', [PromoCode::class, $business]) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business $business */
        $business = $this->route('business');

        return [
            'code' => ['required', 'string', 'max:64'],
            ...$this->reservationIntervalRules($business),
            'resource_id' => ['required', 'uuid', new ResourceBelongsToBusiness($business->id)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        /** @var Business $business */
        $business = $this->route('business');
        $this->withReservationIntervalValidator($validator, $business);
    }
}
