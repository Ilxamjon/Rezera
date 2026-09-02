<?php

namespace App\Http\Requests\Api\V1\SavedSearch;

use App\Domain\Resources\Enums\ResourceType;
use App\Domain\SavedSearches\Enums\SavedSearchAlertChannel;
use App\Domain\SavedSearches\Enums\SavedSearchAlertFrequency;
use App\Domain\SavedSearches\Enums\SavedSearchDateMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'search_query' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'uuid', 'exists:business_categories,id'],
            'city' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:100', 'required_with:latitude,longitude'],
            'business_id' => ['nullable', 'uuid', 'exists:businesses,id'],
            'resource_category_id' => ['nullable', 'uuid', 'exists:resource_groups,id'],
            'resource_id' => ['nullable', 'uuid', 'exists:resources,id'],
            'resource_type' => ['nullable', 'string', Rule::enum(ResourceType::class)],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0', 'gte:min_price'],
            'currency' => ['nullable', 'string', 'size:3'],
            'date_mode' => ['required', 'string', Rule::enum(SavedSearchDateMode::class)],
            'specific_date' => ['nullable', 'date_format:Y-m-d', 'required_if:date_mode,specific_date'],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'required_if:date_mode,date_range'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'required_if:date_mode,date_range', 'after_or_equal:date_from'],
            'days_of_week' => ['nullable', 'array', 'required_if:date_mode,recurring'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'different:start_time'],
            'availability_required' => ['sometimes', 'boolean'],
            'alert_enabled' => ['sometimes', 'boolean'],
            'alert_channel' => ['nullable', 'string', Rule::enum(SavedSearchAlertChannel::class)],
            'alert_frequency' => ['nullable', 'string', Rule::enum(SavedSearchAlertFrequency::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('alert_enabled') && ! $this->boolean('availability_required')) {
                $validator->errors()->add('alert_enabled', __('saved_searches.alert_requires_availability'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function savedSearchData(): array
    {
        return $this->only([
            'name', 'search_query', 'category_id', 'city', 'district', 'region', 'country_code',
            'latitude', 'longitude', 'radius_km', 'business_id', 'resource_category_id', 'resource_id',
            'resource_type', 'min_price', 'max_price', 'currency', 'date_mode', 'specific_date',
            'date_from', 'date_to', 'days_of_week', 'start_time', 'end_time', 'availability_required',
            'alert_enabled', 'alert_channel', 'alert_frequency',
        ]);
    }
}
