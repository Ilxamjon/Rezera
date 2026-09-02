<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Resources\Api\V1\PricingRuleResource;
use App\Models\PricingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingRuleController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::PricingView);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = PricingRule::query()
            ->with(['business:id,name', 'resource:id,name', 'resourceGroup:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->string('business_id')->toString());
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where('name', 'ilike', $search);
        }

        return $this->paginatedResource($query->paginate($perPage), PricingRuleResource::class);
    }

    public function show(PricingRule $rule): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::PricingView);

        $rule->load(['business:id,name', 'resource:id,name', 'resourceGroup:id,name']);

        return $this->success(new PricingRuleResource($rule));
    }
}
