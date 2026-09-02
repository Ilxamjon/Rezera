<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Businesses\UpdateBusinessAction;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Business\UpdateBusinessRequest;
use App\Http\Resources\Api\V1\BusinessManagementResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BusinessManagementController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);

        $query = Business::query()
            ->whereHas('activeMembers', fn ($builder) => $builder->where('user_id', $user->id))
            ->with([
                'category',
                'bookingPolicy',
                'activeMembers' => fn ($builder) => $builder->where('user_id', $user->id),
            ])
            ->orderBy('name');

        return $this->paginatedResource(
            $query->paginate($perPage),
            BusinessManagementResource::class,
        );
    }

    public function show(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('viewManagement', $business);

        $business->load([
            'category',
            'bookingPolicy',
            'hours' => fn ($query) => $query->orderBy('weekday'),
            'activeMembers' => fn ($builder) => $builder->where('user_id', $request->user()->id),
        ]);

        return $this->success(new BusinessManagementResource($business));
    }

    public function update(
        UpdateBusinessRequest $request,
        Business $business,
        UpdateBusinessAction $updateBusiness,
    ): JsonResponse {
        $updated = $updateBusiness->execute($business, $request->validated());

        return $this->success(
            new BusinessManagementResource($updated),
            __('business.updated'),
        );
    }
}
