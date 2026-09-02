<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\PublicResourceResource;
use App\Models\Business;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PublicResourceController extends BaseApiController
{
    public function index(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('viewPublic', $business);

        $perPage = min(max((int) $request->integer('per_page', 50), 1), 100);

        $query = Resource::query()
            ->forBusiness($business->id)
            ->publiclyBookable()
            ->with(['group'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('resource_category_id')) {
            $query->where('resource_group_id', $request->string('resource_category_id'));
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->string('resource_type'));
        }

        return $this->paginatedResource(
            $query->paginate($perPage),
            PublicResourceResource::class,
        );
    }
}
