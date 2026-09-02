<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Business;
use App\Services\Businesses\BusinessOnboardingService;
use App\Services\Businesses\BusinessReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BusinessReadinessController extends BaseApiController
{
    public function show(
        Business $business,
        BusinessOnboardingService $onboarding,
        BusinessReadinessService $readiness,
    ): JsonResponse {
        Gate::authorize('viewManagement', $business);

        $onboarding->sync($business);

        return $this->success($readiness->summary($business->fresh()));
    }
}
