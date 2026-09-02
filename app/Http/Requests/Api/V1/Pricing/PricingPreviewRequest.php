<?php

namespace App\Http\Requests\Api\V1\Pricing;

use App\Http\Requests\Api\V1\Concerns\ValidatesReservationInterval;
use App\Models\Business;
use App\Models\PricingRule;
use App\Rules\ResourceBelongsToBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PricingPreviewRequest extends FormRequest
{
    use ValidatesReservationInterval;

    public function authorize(): bool
    {
        /** @var Business $business */
        $business = $this->route('business');

        return $this->user()?->can('preview', [PricingRule::class, $business]) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business $business */
        $business = $this->route('business');

        return [
            'resource_id' => ['required', 'uuid', new ResourceBelongsToBusiness($business->id)],
            'promo_code' => ['nullable', 'string', 'max:64'],
            ...$this->reservationIntervalRules($business),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        /** @var Business $business */
        $business = $this->route('business');
        $this->withReservationIntervalValidator($validator, $business);
    }
}
