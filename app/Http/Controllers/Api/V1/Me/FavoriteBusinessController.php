<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Favorites\AddFavoriteBusinessAction;
use App\Actions\Favorites\RemoveFavoriteBusinessAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\BusinessDiscoveryResource;
use App\Http\Resources\Api\V1\FavoriteBusinessStateResource;
use App\Models\Business;
use App\Models\User;
use App\Services\Favorites\FavoriteBusinessListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteBusinessController extends BaseApiController
{
    public function index(Request $request, FavoriteBusinessListService $listService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);

        $paginator = $listService->paginateForUser($user, $perPage);

        return $this->paginatedResource($paginator, BusinessDiscoveryResource::class);
    }

    public function store(
        Request $request,
        Business $business,
        AddFavoriteBusinessAction $addFavorite,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $isFavorite = $addFavorite->execute($user, $business);

        return $this->success(new FavoriteBusinessStateResource([
            'business_id' => $business->id,
            'is_favorite' => $isFavorite,
        ]));
    }

    public function destroy(
        Request $request,
        Business $business,
        RemoveFavoriteBusinessAction $removeFavorite,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $isFavorite = $removeFavorite->execute($user, $business);

        return $this->success(new FavoriteBusinessStateResource([
            'business_id' => $business->id,
            'is_favorite' => $isFavorite,
        ]));
    }
}
