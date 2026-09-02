<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Loyalty\AdjustLoyaltyPointsAction;
use App\Actions\Loyalty\CreateLoyaltyRewardAction;
use App\Actions\Loyalty\DeleteLoyaltyRewardAction;
use App\Actions\Loyalty\UpdateLoyaltyProgramAction;
use App\Actions\Loyalty\UpdateLoyaltyRewardAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Loyalty\AdjustLoyaltyPointsRequest;
use App\Http\Requests\Api\V1\Loyalty\StoreLoyaltyRewardRequest;
use App\Http\Requests\Api\V1\Loyalty\UpdateLoyaltyProgramRequest;
use App\Http\Requests\Api\V1\Loyalty\UpdateLoyaltyRewardRequest;
use App\Http\Resources\Api\V1\LoyaltyAccountResource;
use App\Http\Resources\Api\V1\LoyaltyProgramResource;
use App\Http\Resources\Api\V1\LoyaltyRewardResource;
use App\Models\Business;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LoyaltyProgramController extends BaseApiController
{
    public function show(Business $business, LoyaltyService $loyaltyService): JsonResponse
    {
        Gate::authorize('viewProgram', [LoyaltyProgram::class, $business]);

        $program = $loyaltyService->getProgram($business);

        if ($program === null) {
            return $this->success(null);
        }

        return $this->success(new LoyaltyProgramResource($program));
    }

    public function update(
        UpdateLoyaltyProgramRequest $request,
        Business $business,
        UpdateLoyaltyProgramAction $action,
    ): JsonResponse {
        Gate::authorize('updateProgram', [LoyaltyProgram::class, $business]);

        $program = $action->execute($business, $request->user(), $request->validated());

        return $this->success(new LoyaltyProgramResource($program), __('loyalty.program_updated'));
    }

    public function customerLoyalty(Business $business, User $user, LoyaltyService $loyaltyService): JsonResponse
    {
        Gate::authorize('viewCustomerLoyalty', [LoyaltyProgram::class, $business]);

        $account = LoyaltyAccount::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->first();

        $program = $loyaltyService->getProgram($business);

        if ($account === null) {
            $account = new LoyaltyAccount([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_redeemed' => 0,
            ]);
        }

        $account->setRelation('program', $program);

        return $this->success(new LoyaltyAccountResource($account));
    }

    public function adjust(
        AdjustLoyaltyPointsRequest $request,
        Business $business,
        User $user,
        AdjustLoyaltyPointsAction $action,
    ): JsonResponse {
        Gate::authorize('adjustPoints', [LoyaltyProgram::class, $business]);

        $validated = $request->validated();
        $action->execute($business, $user, $request->user(), $validated['points'], $validated['reason']);

        return $this->success(null, __('loyalty.points_adjusted'));
    }
}
