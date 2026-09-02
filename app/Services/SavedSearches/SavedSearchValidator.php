<?php

namespace App\Services\SavedSearches;

use App\Models\Business;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class SavedSearchValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?SavedSearch $existing = null): void
    {
        $businessId = $data['business_id'] ?? $existing?->business_id;
        $resourceCategoryId = $data['resource_category_id'] ?? $existing?->resource_category_id;
        $resourceId = $data['resource_id'] ?? $existing?->resource_id;

        if ($businessId !== null) {
            $business = Business::query()->find($businessId);

            if ($business === null || ! $business->isPubliclyVisible()) {
                throw ValidationException::withMessages([
                    'business_id' => [__('saved_searches.business_not_available')],
                ]);
            }
        }

        if ($resourceCategoryId !== null) {
            $group = ResourceGroup::query()
                ->whereKey($resourceCategoryId)
                ->whereNull('deleted_at')
                ->first();

            if ($group === null) {
                throw ValidationException::withMessages([
                    'resource_category_id' => [__('saved_searches.resource_category_not_found')],
                ]);
            }

            if ($businessId !== null && $group->business_id !== $businessId) {
                throw ValidationException::withMessages([
                    'resource_category_id' => [__('saved_searches.resource_category_business_mismatch')],
                ]);
            }
        }

        if ($resourceId !== null) {
            $resource = Resource::query()
                ->whereKey($resourceId)
                ->whereNull('deleted_at')
                ->first();

            if ($resource === null) {
                throw ValidationException::withMessages([
                    'resource_id' => [__('saved_searches.resource_not_found')],
                ]);
            }

            if ($businessId !== null && $resource->business_id !== $businessId) {
                throw ValidationException::withMessages([
                    'resource_id' => [__('saved_searches.resource_business_mismatch')],
                ]);
            }

            if ($resourceCategoryId !== null && $resource->resource_group_id !== $resourceCategoryId) {
                throw ValidationException::withMessages([
                    'resource_id' => [__('saved_searches.resource_category_mismatch')],
                ]);
            }
        }

        if (($data['availability_required'] ?? $existing?->availability_required) === true) {
            $startTime = $data['start_time'] ?? $existing?->start_time;
            $endTime = $data['end_time'] ?? $existing?->end_time;

            if ($startTime === null || $endTime === null) {
                throw ValidationException::withMessages([
                    'start_time' => [__('saved_searches.availability_requires_time_window')],
                ]);
            }

            if ($startTime === $endTime) {
                throw ValidationException::withMessages([
                    'end_time' => [__('saved_searches.end_time_must_differ')],
                ]);
            }
        }
    }

    public function assertWithinUserLimit(User $user): void
    {
        $max = (int) config('rezera.saved_searches.max_per_user', 25);
        $count = SavedSearch::query()->where('user_id', $user->id)->count();

        if ($count >= $max) {
            throw ValidationException::withMessages([
                'name' => [__('saved_searches.limit_reached', ['max' => $max])],
            ]);
        }
    }
}
