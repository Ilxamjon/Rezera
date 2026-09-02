<?php

namespace App\Http\Requests\Api\V1\Pricing;

use App\Domain\Pricing\Enums\PricingType;
use App\Models\Business;
use App\Models\PricingRule;
use App\Rules\ResourceBelongsToBusiness;
use App\Rules\ResourceCategoryBelongsToBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Business $business */
        $business = $this->route('business');
        /** @var PricingRule $rule */
        $rule = $this->route('rule');

        return $this->user()?->can('update', [$rule, $business]) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business $business */
        $business = $this->route('business');

        return [
            'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pricing_type' => ['sometimes', Rule::enum(PricingType::class)],
            'price' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'resource_id' => ['nullable', 'uuid', new ResourceBelongsToBusiness($business->id)],
            'resource_category_id' => ['nullable', 'uuid', new ResourceCategoryBelongsToBusiness($business->id)],
            'day_of_week' => ['nullable', 'integer', 'between:1,7'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'specific_date' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
