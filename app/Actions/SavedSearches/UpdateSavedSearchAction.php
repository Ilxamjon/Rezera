<?php

namespace App\Actions\SavedSearches;

use App\Models\SavedSearch;
use App\Models\User;
use App\Services\SavedSearches\SavedSearchAlertService;
use App\Services\SavedSearches\SavedSearchValidator;

final class UpdateSavedSearchAction
{
    public function __construct(
        private readonly SavedSearchValidator $validator,
        private readonly SavedSearchAlertService $alertService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, SavedSearch $savedSearch, array $data): SavedSearch
    {
        if ($savedSearch->user_id !== $user->id) {
            abort(404);
        }

        $merged = array_merge($savedSearch->only($savedSearch->getFillable()), $data);
        $this->validator->validate($merged, $savedSearch);

        $filterFields = [
            'search_query', 'category_id', 'city', 'district', 'region', 'country_code',
            'latitude', 'longitude', 'radius_km', 'business_id', 'resource_category_id',
            'resource_id', 'resource_type', 'min_price', 'max_price', 'currency',
            'date_mode', 'specific_date', 'date_from', 'date_to', 'days_of_week',
            'start_time', 'end_time', 'availability_required',
        ];

        $filtersChanged = collect($filterFields)->contains(
            fn (string $field): bool => array_key_exists($field, $data)
                && $savedSearch->getAttribute($field) != $data[$field],
        );

        $savedSearch->fill($data);
        $savedSearch->save();

        if ($filtersChanged) {
            $this->alertService->invalidateMatchState($savedSearch);
        }

        return $savedSearch->fresh();
    }
}
