<?php

namespace App\Http\Requests\Api\V1\Promo;

use App\Domain\Promotions\Enums\DiscountType;
use App\Models\Business;
use App\Models\PromoCode;
use App\Support\Promotions\PromoCodeNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePromoCodeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => PromoCodeNormalizer::normalize((string) $this->input('code')),
            ]);
        }
    }

    public function authorize(): bool
    {
        /** @var Business|null $business */
        $business = $this->route('business');
        /** @var PromoCode $promo */
        $promo = $this->route('promo');

        if ($business !== null) {
            return $this->user()?->can('update', [$promo, $business]) === true;
        }

        return $this->user()?->can('updateAdmin', PromoCode::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business|null $business */
        $business = $this->route('business');
        /** @var PromoCode $promo */
        $promo = $this->route('promo');
        $businessId = $business?->id ?? $promo->business_id;

        return [
            'code' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('promo_codes', 'code')
                    ->ignore($promo->id)
                    ->where(function ($query) use ($businessId): void {
                        if ($businessId === null) {
                            $query->whereNull('business_id')->whereNull('deleted_at');
                        } else {
                            $query->where('business_id', $businessId)->whereNull('deleted_at');
                        }
                    }),
            ],
            'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['sometimes', Rule::enum(DiscountType::class)],
            'discount_value' => ['sometimes', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'minimum_amount' => ['nullable', 'integer', 'min:0'],
            'maximum_discount' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('discount_type');
            $value = $this->input('discount_value');

            if ($type === DiscountType::Percentage->value && $value !== null && (int) $value > 100) {
                $validator->errors()->add('discount_value', __('promotions.percentage_max'));
            }
        });
    }
}
