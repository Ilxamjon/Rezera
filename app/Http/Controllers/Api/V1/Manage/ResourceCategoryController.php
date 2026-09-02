<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Resources\CreateResourceCategoryAction;
use App\Actions\Resources\DeleteResourceCategoryAction;
use App\Actions\Resources\UpdateResourceCategoryAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Resource\StoreResourceCategoryRequest;
use App\Http\Requests\Api\V1\Resource\UpdateResourceCategoryRequest;
use App\Http\Resources\Api\V1\ResourceCategoryResource;
use App\Models\Business;
use App\Models\ResourceGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ResourceCategoryController extends BaseApiController
{
    public function index(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('viewAny', [ResourceGroup::class, $business]);

        $query = ResourceGroup::query()
            ->where('business_id', $business->id)
            ->withCount('resources')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $this->success(ResourceCategoryResource::collection($query->get()));
    }

    public function store(
        StoreResourceCategoryRequest $request,
        Business $business,
        CreateResourceCategoryAction $createCategory,
    ): JsonResponse {
        $category = $createCategory->execute($business, $request->validated());

        return $this->created(
            new ResourceCategoryResource($category),
            __('resources.category_created'),
        );
    }

    public function show(Business $business, ResourceGroup $category): JsonResponse
    {
        $this->ensureBelongsToBusiness($business, $category);
        Gate::authorize('view', [$category, $business]);

        $category->loadCount('resources');

        return $this->success(new ResourceCategoryResource($category));
    }

    public function update(
        UpdateResourceCategoryRequest $request,
        Business $business,
        ResourceGroup $category,
        UpdateResourceCategoryAction $updateCategory,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $category);

        $updated = $updateCategory->execute($category, $request->validated());

        return $this->success(
            new ResourceCategoryResource($updated),
            __('resources.category_updated'),
        );
    }

    public function destroy(
        Business $business,
        ResourceGroup $category,
        DeleteResourceCategoryAction $deleteCategory,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $category);
        Gate::authorize('delete', [$category, $business]);

        $deleteCategory->execute($category);

        return $this->success(null, __('resources.category_deleted'));
    }

    private function ensureBelongsToBusiness(Business $business, ResourceGroup $category): void
    {
        if ($category->business_id !== $business->id) {
            abort(404);
        }
    }
}
