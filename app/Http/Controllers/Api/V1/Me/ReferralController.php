<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\ReferralResource;
use App\Http\Resources\Api\V1\ReferralSummaryResource;
use App\Models\Referral;
use App\Services\Referrals\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReferralController extends BaseApiController
{
    public function show(Request $request, ReferralService $referralService): JsonResponse
    {
        Gate::authorize('viewOwn', Referral::class);

        $code = $referralService->getOrCreateCode($request->user());
        $summary = $referralService->summaryForUser($request->user());

        return $this->success(new ReferralSummaryResource([
            'code' => $code->code,
            ...$summary,
        ]));
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewOwn', Referral::class);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = Referral::query()
            ->where('referrer_user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $this->paginatedResource($query->paginate($perPage), ReferralResource::class);
    }
}
