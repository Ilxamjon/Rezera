<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Requests\Api\V1\Admin\AdminCategoryRequest;
use App\Http\Resources\Api\V1\Admin\AdminCategoryResource;
use App\Models\BusinessCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::CategoriesManage);

        $categories = BusinessCategory::query()
            ->withCount('businesses')
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();

        return $this->success(AdminCategoryResource::collection($categories));
    }

    public function store(AdminCategoryRequest $request, CreateAuditLogAction $audit): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::CategoriesManage);

        $category = BusinessCategory::query()->create($request->validated());
        $audit->execute('category.created', 'business_category', $category->id, $request->user(), null, $category->only(['slug', 'name']), request: $request);

        return $this->created(new AdminCategoryResource($category));
    }

    public function show(BusinessCategory $category): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::CategoriesManage);
        $category->loadCount('businesses');

        return $this->success(new AdminCategoryResource($category));
    }

    public function update(AdminCategoryRequest $request, BusinessCategory $category, CreateAuditLogAction $audit): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::CategoriesManage);
        $old = $category->only(['slug', 'name', 'is_active']);
        $category->update($request->validated());
        $audit->execute('category.updated', 'business_category', $category->id, $request->user(), $old, $category->fresh()->only(['slug', 'name', 'is_active']), request: $request);

        return $this->success(new AdminCategoryResource($category->fresh()));
    }

    public function destroy(BusinessCategory $category, Request $request, CreateAuditLogAction $audit): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::CategoriesManage);

        if ($category->businesses()->exists()) {
            throw ValidationException::withMessages([
                'category' => [__('admin.category_in_use')],
            ]);
        }

        $category->update(['is_active' => false]);
        $audit->execute('category.deactivated', 'business_category', $category->id, $request->user(), request: $request);

        return $this->success(new AdminCategoryResource($category->fresh()));
    }
}
