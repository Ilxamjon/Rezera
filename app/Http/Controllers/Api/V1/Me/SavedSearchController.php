<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\SavedSearches\CreateSavedSearchAction;
use App\Actions\SavedSearches\DeleteSavedSearchAction;
use App\Actions\SavedSearches\UpdateSavedSearchAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\SavedSearch\StoreSavedSearchRequest;
use App\Http\Requests\Api\V1\SavedSearch\UpdateSavedSearchRequest;
use App\Http\Resources\Api\V1\SavedSearchResource;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSearchController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);

        $paginator = SavedSearch::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->paginatedResource($paginator, SavedSearchResource::class);
    }

    public function store(
        StoreSavedSearchRequest $request,
        CreateSavedSearchAction $createSavedSearch,
    ): JsonResponse {
        $savedSearch = $createSavedSearch->execute(
            $request->user(),
            $request->savedSearchData(),
        );

        return $this->created(new SavedSearchResource($savedSearch), __('saved_searches.created'));
    }

    public function show(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        $this->authorize('view', $savedSearch);

        return $this->success(new SavedSearchResource($savedSearch));
    }

    public function update(
        UpdateSavedSearchRequest $request,
        SavedSearch $savedSearch,
        UpdateSavedSearchAction $updateSavedSearch,
    ): JsonResponse {
        $this->authorize('update', $savedSearch);

        $updated = $updateSavedSearch->execute(
            $request->user(),
            $savedSearch,
            $request->savedSearchData(),
        );

        return $this->success(new SavedSearchResource($updated), __('saved_searches.updated'));
    }

    public function destroy(
        Request $request,
        SavedSearch $savedSearch,
        DeleteSavedSearchAction $deleteSavedSearch,
    ): JsonResponse {
        $this->authorize('delete', $savedSearch);

        $deleteSavedSearch->execute($request->user(), $savedSearch);

        return $this->success(null, __('saved_searches.deleted'));
    }
}
