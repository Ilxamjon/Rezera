<?php

namespace App\Http\Requests\Api\V1\Promo;

use App\Domain\Promotions\Enums\DiscountType;
use App\Models\Business;
use App\Models\PromoCode;
use App\Support\Promotions\PromoCodeNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePromoCodeRequest extends FormRequest
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

        if ($business !== null) {
            return $this->user()?->can('create', [PromoCode::class, $business]) === true;
        }

        return $this->user()?->can('createAdmin', PromoCode::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business|null $business */
        $business = $this->route('business');
        $businessId = $business?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('promo_codes', 'code')
                    ->where(function ($query) use ($businessId): void {
                        if ($businessId === null) {
                            $query->whereNull('business_id')->whereNull('deleted_at');
                        } else {
                            $query->where('business_id', $businessId)->whereNull('deleted_at');
                        }
                    }),
            ],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'integer', 'min:1'],
            'currency' => [
                Rule::requiredIf(fn () => $this->input('discount_type') === DiscountType::Fixed->value),
                'nullable',
                'string',
                'size:3',
            ],
            'minimum_amount' => ['nullable', 'integer', 'min:0'],
            'maximum_discount' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'business_id' => ['nullable', 'uuid', 'exists:businesses,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('discount_type') === DiscountType::Percentage->value) {
                if ((int) $this->input('discount_value') > 100) {
                    $validator->errors()->add('discount_value', __('promotions.percentage_max'));
                }
            }
        });
    }

}
