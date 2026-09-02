<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Loyalty\CreateLoyaltyRewardAction;
use App\Actions\Loyalty\DeleteLoyaltyRewardAction;
use App\Actions\Loyalty\UpdateLoyaltyRewardAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Loyalty\StoreLoyaltyRewardRequest;
use App\Http\Requests\Api\V1\Loyalty\UpdateLoyaltyRewardRequest;
use App\Http\Resources\Api\V1\LoyaltyRewardResource;
use App\Models\Business;
use App\Models\LoyaltyReward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LoyaltyRewardController extends BaseApiController
{
    public function index(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('viewAnyManage', [LoyaltyReward::class, $business]);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = LoyaltyReward::query()
            ->where('business_id', $business->id)
            ->orderByDesc('created_at');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $this->paginatedResource($query->paginate($perPage), LoyaltyRewardResource::class);
    }

    public function store(
        StoreLoyaltyRewardRequest $request,
        Business $business,
        CreateLoyaltyRewardAction $action,
    ): JsonResponse {
        Gate::authorize('create', [LoyaltyReward::class, $business]);

        $reward = $action->execute($business, $request->user(), $request->validated());

        return $this->created(new LoyaltyRewardResource($reward), __('loyalty.reward_created'));
    }

    public function show(Business $business, LoyaltyReward $reward): JsonResponse
    {
        $this->ensureBelongsToBusiness($business, $reward);
        Gate::authorize('view', [$reward, $business]);

        return $this->success(new LoyaltyRewardResource($reward));
    }

    public function update(
        UpdateLoyaltyRewardRequest $request,
        Business $business,
        LoyaltyReward $reward,
        UpdateLoyaltyRewardAction $action,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $reward);
        Gate::authorize('update', [$reward, $business]);

        $updated = $action->execute($reward, $request->user(), $request->validated());

        return $this->success(new LoyaltyRewardResource($updated), __('loyalty.reward_updated'));
    }

    public function destroy(
        Business $business,
        LoyaltyReward $reward,
        DeleteLoyaltyRewardAction $action,
        Request $request,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $reward);
        Gate::authorize('delete', [$reward, $business]);

        $action->execute($reward, $request->user());

        return $this->noContent();
    }

    private function ensureBelongsToBusiness(Business $business, LoyaltyReward $reward): void
    {
        if ($reward->business_id !== $business->id) {
            abort(404);
        }
    }
}
