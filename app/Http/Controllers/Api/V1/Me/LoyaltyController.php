<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Domain\Loyalty\Enums\LoyaltyRedemptionStatus;
use App\Domain\Loyalty\Enums\LoyaltyTransactionType;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\LoyaltyAccountResource;
use App\Http\Resources\Api\V1\LoyaltyRedemptionResource;
use App\Http\Resources\Api\V1\LoyaltyTransactionResource;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends BaseApiController
{
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => ['required', 'uuid', 'exists:businesses,id'],
        ]);

        $account = LoyaltyAccount::query()
            ->where('business_id', $validated['business_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        $program = LoyaltyProgram::query()
            ->where('business_id', $validated['business_id'])
            ->first();

        if ($account === null && $program === null) {
            return $this->success([
                'business_id' => $validated['business_id'],
                'program' => null,
                'balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_redeemed' => 0,
            ]);
        }

        if ($account === null) {
            $account = new LoyaltyAccount([
                'business_id' => $validated['business_id'],
                'user_id' => $request->user()->id,
                'balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_redeemed' => 0,
            ]);
        }

        $account->setRelation('program', $program);

        return $this->success(new LoyaltyAccountResource($account));
    }

    public function transactions(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = LoyaltyTransaction::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->string('business_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', LoyaltyTransactionType::from($request->string('type')));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        return $this->paginatedResource($query->paginate($perPage), LoyaltyTransactionResource::class);
    }

    public function redemptions(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = LoyaltyRedemption::query()
            ->with('reward')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->string('business_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', LoyaltyRedemptionStatus::from($request->string('status')));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        return $this->paginatedResource($query->paginate($perPage), LoyaltyRedemptionResource::class);
    }
}
