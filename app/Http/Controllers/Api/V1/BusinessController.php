<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Businesses\CreateBusinessAction;
use App\Http\Requests\Api\V1\Business\BusinessDiscoveryRequest;
use App\Http\Requests\Api\V1\Business\StoreBusinessRequest;
use App\Http\Resources\Api\V1\BusinessDiscoveryResource;
use App\Http\Resources\Api\V1\BusinessManagementResource;
use App\Http\Resources\Api\V1\PublicBusinessResource;
use App\Models\Business;
use App\Services\Availability\BusinessHoursResolver;
use App\Services\Discovery\BusinessDiscoveryFilters;
use App\Services\Discovery\BusinessDiscoveryService;
use App\Services\Favorites\FavoriteBusinessService;
use App\Services\Reviews\ReviewRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BusinessController extends BaseApiController
{
    public function index(BusinessDiscoveryRequest $request, BusinessDiscoveryService $discoveryService): JsonResponse
    {
        if ($request->boolean('favorites') && $request->user() === null) {
            abort(401, __('auth.unauthenticated'));
        }

        $filters = BusinessDiscoveryFilters::fromRequest($request, $request->user()?->id);
        $paginator = $discoveryService->discover($filters);

        return $this->paginatedResource($paginator, BusinessDiscoveryResource::class);
    }

    public function show(
        Request $request,
        Business $business,
        BusinessHoursResolver $businessHoursResolver,
        FavoriteBusinessService $favoriteBusinessService,
        ReviewRatingService $reviewRatingService,
    ): JsonResponse {
        Gate::authorize('viewPublic', $business);

        $business->load([
            'category',
            'hours' => fn ($query) => $query->orderBy('weekday'),
            'resourceGroups' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name'),
            'resources' => fn ($query) => $query->publiclyBookable()->orderBy('sort_order')->orderBy('name'),
        ]);

        $business->setAttribute('price_from', $business->resources->min('hourly_rate_amount'));
        $business->setAttribute('open_now', $businessHoursResolver->isOpenAt($business));

        if ($request->user() !== null) {
            $business->setAttribute(
                'is_favorite',
                $favoriteBusinessService->isFavorite($request->user(), $business),
            );
        }

        $summary = $reviewRatingService->summaryForBusiness($business);
        $business->setAttribute('rating_average', $summary['average']);
        $business->setAttribute('rating_count', $summary['count']);
        $business->setAttribute('rating_distribution', $summary['distribution']);

        return $this->success(new PublicBusinessResource($business));
    }

    public function store(StoreBusinessRequest $request, CreateBusinessAction $createBusiness): JsonResponse
    {
        $business = $createBusiness->execute($request->user(), $request->validated());

        return $this->created(
            new BusinessManagementResource($business),
            __('business.created'),
        );
    }
}
