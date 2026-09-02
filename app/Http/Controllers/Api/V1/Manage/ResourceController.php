<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Resources\CreateResourceAction;
use App\Actions\Resources\DeleteResourceAction;
use App\Actions\Resources\UpdateResourceAction;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Resource\StoreResourceRequest;
use App\Http\Requests\Api\V1\Resource\UpdateResourceRequest;
use App\Http\Resources\Api\V1\ResourceManagementResource;
use App\Models\Business;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ResourceController extends BaseApiController
{
    public function index(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('viewAny', [Resource::class, $business]);

        $perPage = min(max((int) $request->integer('per_page', 50), 1), 100);

        $query = Resource::query()
            ->forBusiness($business->id)
            ->with(['group'])
            ->orderBy($this->resolveSortColumn($request), $this->resolveSortDirection($request));

        if ($request->filled('resource_category_id')) {
            $query->where('resource_group_id', $request->string('resource_category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->has('is_active')) {
            $query->where(
                'status',
                $request->boolean('is_active') ? ResourceStatus::Active : ResourceStatus::Inactive,
            );
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'ilike', $search)
                    ->orWhere('code', 'ilike', $search);
            });
        }

        return $this->paginatedResource(
            $query->paginate($perPage),
            ResourceManagementResource::class,
        );
    }

    public function store(
        StoreResourceRequest $request,
        Business $business,
        CreateResourceAction $createResource,
    ): JsonResponse {
        $resource = $createResource->execute($business, $request->validated());

        return $this->created(
            new ResourceManagementResource($resource),
            __('resources.resource_created'),
        );
    }

    public function show(Business $business, Resource $resource): JsonResponse
    {
        $this->ensureBelongsToBusiness($business, $resource);
        Gate::authorize('view', [$resource, $business]);

        $resource->load('group');

        return $this->success(new ResourceManagementResource($resource));
    }

    public function update(
        UpdateResourceRequest $request,
        Business $business,
        Resource $resource,
        UpdateResourceAction $updateResource,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $resource);

        $updated = $updateResource->execute($resource, $request->validated());

        return $this->success(
            new ResourceManagementResource($updated),
            __('resources.resource_updated'),
        );
    }

    public function destroy(
        Business $business,
        Resource $resource,
        DeleteResourceAction $deleteResource,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $resource);
        Gate::authorize('delete', [$resource, $business]);

        $deleteResource->execute($resource);

        return $this->success(null, __('resources.resource_deleted'));
    }

    private function ensureBelongsToBusiness(Business $business, Resource $resource): void
    {
        if ($resource->business_id !== $business->id) {
            abort(404);
        }
    }

    private function resolveSortColumn(Request $request): string
    {
        return match ($request->string('sort')->toString()) {
            'name' => 'name',
            'code' => 'code',
            'status' => 'status',
            default => 'sort_order',
        };
    }

    private function resolveSortDirection(Request $request): string
    {
        return $request->string('direction')->lower()->toString() === 'desc' ? 'desc' : 'asc';
    }
}
