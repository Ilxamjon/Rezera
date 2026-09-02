<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\ReferralResource;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReferralController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAnyAdmin', Referral::class);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = Referral::query()
            ->with(['referrer:id,name', 'referred:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('referrer_user_id')) {
            $query->where('referrer_user_id', $request->string('referrer_user_id'));
        }

        return $this->paginatedResource($query->paginate($perPage), ReferralResource::class);
    }

    public function show(Referral $referral): JsonResponse
    {
        Gate::authorize('viewAdmin', $referral);

        $referral->load(['referrer:id,name', 'referred:id,name', 'referralCode']);

        return $this->success(new ReferralResource($referral));
    }
}
