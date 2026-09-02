<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\BusinessOnboardingResource;
use App\Models\Business;
use App\Services\Businesses\BusinessOnboardingService;
use App\Services\Businesses\BusinessReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BusinessOnboardingController extends BaseApiController
{
    public function show(
        Business $business,
        BusinessOnboardingService $onboarding,
        BusinessReadinessService $readiness,
    ): JsonResponse {
        Gate::authorize('viewManagement', $business);

        $business = $onboarding->sync($business);
        $progress = $onboarding->progress($business);

        return $this->success(new BusinessOnboardingResource([
            ...$progress,
            'ready_for_verification' => $onboarding->isReadyForVerification($business),
            'ready_for_reservations' => $readiness->isReadyForReservations($business),
            'verification_status' => $business->verification_status?->value,
            'onboarding_completed_at' => $business->onboarding_completed_at?->toIso8601String(),
        ]));
    }
}
