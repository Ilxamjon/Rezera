<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\LoyaltyRedemptionResource;
use App\Http\Resources\Api\V1\LoyaltyRewardResource;
use App\Models\Business;
use App\Models\LoyaltyReward;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LoyaltyRewardController extends BaseApiController
{
    public function index(Request $request, Business $business): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = LoyaltyReward::query()
            ->where('business_id', $business->id)
            ->active()
            ->orderBy('points_cost');

        if ($request->filled('reward_type')) {
            $query->where('reward_type', $request->string('reward_type'));
        }

        return $this->paginatedResource($query->paginate($perPage), LoyaltyRewardResource::class);
    }

    public function redeem(
        Request $request,
        Business $business,
        LoyaltyReward $reward,
        LoyaltyService $loyaltyService,
    ): JsonResponse {
        if ($reward->business_id !== $business->id) {
            abort(404);
        }

        Gate::authorize('redeem', [$reward, $business]);

        $redemption = $loyaltyService->redeemReward($request->user(), $business, $reward);

        return $this->created(new LoyaltyRedemptionResource($redemption), __('loyalty.reward_redeemed'));
    }
}
