<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Businesses\SubmitBusinessVerificationAction;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Business\SubmitBusinessVerificationRequest;
use App\Http\Resources\Api\V1\BusinessVerificationResource;
use App\Models\Business;
use App\Services\Businesses\BusinessReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BusinessVerificationController extends BaseApiController
{
    public function show(Business $business, BusinessReadinessService $readiness): JsonResponse
    {
        Gate::authorize('viewManagement', $business);

        $latest = $business->latestVerification;

        $nextAction = match ($business->verification_status) {
            BusinessVerificationStatus::Unverified, BusinessVerificationStatus::Rejected => $readiness->summary($business)['ready_for_verification']
                ? 'submit_verification'
                : 'complete_onboarding',
            BusinessVerificationStatus::Pending => 'await_review',
            BusinessVerificationStatus::Verified => 'none',
            default => null,
        };

        if ($latest === null) {
            return $this->success([
                'status' => $business->verification_status?->value,
                'submitted_at' => null,
                'reviewed_at' => $business->reviewed_at?->toIso8601String(),
                'rejection_reason' => $business->rejection_reason,
                'next_action' => $nextAction,
                'readiness' => $readiness->summary($business),
            ]);
        }

        return $this->success(new BusinessVerificationResource($latest->setAttribute('next_action', $nextAction)));
    }

    public function store(
        SubmitBusinessVerificationRequest $request,
        Business $business,
        SubmitBusinessVerificationAction $submitVerification,
    ): JsonResponse {
        Gate::authorize('manageVerification', $business);

        $verification = $submitVerification->execute($business, $request->user());

        return $this->created(
            new BusinessVerificationResource($verification->setAttribute('next_action', 'await_review')),
            __('onboarding.verification_submitted'),
        );
    }

    public function resubmit(
        SubmitBusinessVerificationRequest $request,
        Business $business,
        SubmitBusinessVerificationAction $submitVerification,
    ): JsonResponse {
        Gate::authorize('manageVerification', $business);

        $verification = $submitVerification->execute($business, $request->user(), resubmit: true);

        return $this->success(
            new BusinessVerificationResource($verification->setAttribute('next_action', 'await_review')),
            __('onboarding.verification_resubmitted'),
        );
    }
}
