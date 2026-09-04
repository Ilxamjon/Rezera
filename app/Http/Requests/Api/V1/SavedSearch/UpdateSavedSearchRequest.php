<?php

namespace App\Http\Requests\Api\V1\SavedSearch;

use App\Domain\SavedSearches\Enums\SavedSearchDateMode;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSavedSearchRequest extends StoreSavedSearchRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'] = ['sometimes', 'string', 'max:120'];
        $rules['date_mode'] = ['sometimes', 'string', Rule::enum(SavedSearchDateMode::class)];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->has('alert_enabled') && $this->boolean('alert_enabled')) {
                $availabilityRequired = $this->has('availability_required')
                    ? $this->boolean('availability_required')
                    : (bool) $this->route('savedSearch')?->availability_required;

                if (! $availabilityRequired) {
                    $validator->errors()->add('alert_enabled', __('saved_searches.alert_requires_availability'));
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function savedSearchData(): array
    {
        return collect($this->only([
            'name', 'search_query', 'category_id', 'city', 'district', 'region', 'country_code',
            'latitude', 'longitude', 'radius_km', 'business_id', 'resource_category_id', 'resource_id',
            'resource_type', 'min_price', 'max_price', 'currency', 'date_mode', 'specific_date',
            'date_from', 'date_to', 'days_of_week', 'start_time', 'end_time', 'availability_required',
            'alert_enabled', 'alert_channel', 'alert_frequency',
        ]))->filter(fn ($value) => $value !== null)->all();
    }
}
