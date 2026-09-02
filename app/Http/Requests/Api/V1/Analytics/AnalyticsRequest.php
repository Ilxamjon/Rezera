<?php

namespace App\Http\Requests\Api\V1\Analytics;

use App\Domain\Analytics\Enums\AnalyticsDatePreset;
use App\Domain\Analytics\Enums\AnalyticsGranularity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preset' => ['sometimes', 'string', Rule::enum(AnalyticsDatePreset::class)],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            'compare_from' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'required_with:compare_to'],
            'compare_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'required_with:compare_from', 'after_or_equal:compare_from'],
            'granularity' => ['sometimes', 'string', Rule::enum(AnalyticsGranularity::class)],
            'resource_id' => ['sometimes', 'uuid', 'exists:resources,id'],
            'resource_category_id' => ['sometimes', 'uuid', 'exists:resource_groups,id'],
            'status' => ['sometimes', 'string', 'max:32'],
            'payment_status' => ['sometimes', 'string', 'max:32'],
            'customer_id' => ['sometimes', 'uuid', 'exists:users,id'],
            'sort' => ['sometimes', 'string', 'max:64'],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'compare_previous' => ['sometimes', 'boolean'],
        ];
    }
}
