<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Businesses\ApproveBusinessVerificationAction;
use App\Actions\Businesses\RejectBusinessVerificationAction;
use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Requests\Api\V1\Admin\RejectBusinessVerificationRequest;
use App\Http\Resources\Api\V1\Admin\AdminBusinessVerificationResource;
use App\Models\BusinessVerification;
use App\Services\Businesses\BusinessOnboardingService;
use App\Services\Businesses\BusinessReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessVerificationController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::BusinessesView);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = BusinessVerification::query()
            ->with(['business.category', 'submittedBy', 'reviewedBy'])
            ->orderByDesc('submitted_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->string('business_id')->toString());
        }

        if ($request->filled('category_id')) {
            $query->whereHas('business', fn ($builder) => $builder->where('category_id', $request->string('category_id')->toString()));
        }

        if ($request->filled('reviewed_by')) {
            $query->where('reviewed_by_user_id', $request->string('reviewed_by')->toString());
        }

        if ($request->filled('from')) {
            $query->where('submitted_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('submitted_at', '<=', $request->date('to'));
        }

        return $this->paginatedResource($query->paginate($perPage), AdminBusinessVerificationResource::class);
    }

    public function show(
        BusinessVerification $verification,
        BusinessOnboardingService $onboarding,
        BusinessReadinessService $readiness,
    ): JsonResponse {
        $this->authorizePlatform(PlatformPermission::BusinessesView);

        $verification->load(['business.category', 'submittedBy', 'reviewedBy']);
        $business = $verification->business;

        $history = BusinessVerification::query()
            ->where('business_id', $verification->business_id)
            ->orderByDesc('submitted_at')
            ->limit(20)
            ->get(['id', 'status', 'submitted_at', 'reviewed_at', 'rejection_reason']);

        $verification->setAttribute('history', $history->map(fn (BusinessVerification $item) => [
            'id' => $item->id,
            'status' => $item->status?->value,
            'submitted_at' => $item->submitted_at?->toIso8601String(),
            'reviewed_at' => $item->reviewed_at?->toIso8601String(),
            'rejection_reason' => $item->rejection_reason,
        ]));
        $verification->setAttribute('readiness', $business ? $readiness->summary($onboarding->sync($business)) : null);

        return $this->success(new AdminBusinessVerificationResource($verification));
    }

    public function approve(
        Request $request,
        BusinessVerification $verification,
        ApproveBusinessVerificationAction $approve,
    ): JsonResponse {
        $this->authorizePlatform(PlatformPermission::BusinessesManage);

        $updated = $approve->execute($verification, $request->user(), $request);

        return $this->success(new AdminBusinessVerificationResource($updated->load(['business.category', 'submittedBy', 'reviewedBy'])));
    }

    public function reject(
        RejectBusinessVerificationRequest $request,
        BusinessVerification $verification,
        RejectBusinessVerificationAction $reject,
    ): JsonResponse {
        $this->authorizePlatform(PlatformPermission::BusinessesManage);

        $updated = $reject->execute(
            verification: $verification,
            actor: $request->user(),
            reason: $request->validated('reason'),
            request: $request,
            adminNotes: $request->validated('admin_notes'),
        );

        return $this->success(new AdminBusinessVerificationResource($updated->load(['business.category', 'submittedBy', 'reviewedBy'])));
    }
}
