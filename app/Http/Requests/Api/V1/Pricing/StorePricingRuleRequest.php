<?php

namespace App\Http\Requests\Api\V1\Pricing;

use App\Domain\Pricing\Enums\PricingType;
use App\Models\Business;
use App\Models\PricingRule;
use App\Rules\ResourceBelongsToBusiness;
use App\Rules\ResourceCategoryBelongsToBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Business $business */
        $business = $this->route('business');

        return $this->user()?->can('create', [PricingRule::class, $business]) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business $business */
        $business = $this->route('business');

        return [
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pricing_type' => ['required', Rule::enum(PricingType::class)],
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'resource_id' => ['nullable', 'uuid', new ResourceBelongsToBusiness($business->id), 'prohibits:resource_category_id'],
            'resource_category_id' => ['nullable', 'uuid', new ResourceCategoryBelongsToBusiness($business->id), 'prohibits:resource_id'],
            'day_of_week' => ['nullable', 'integer', 'between:1,7'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'specific_date' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
