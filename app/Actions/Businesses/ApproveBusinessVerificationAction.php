<?php

namespace App\Actions\Businesses;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Businesses\Enums\VerificationRequestStatus;
use App\Events\Businesses\BusinessBecameReservationReady;
use App\Events\Businesses\BusinessVerificationApproved;
use App\Exceptions\Businesses\BusinessVerificationException;
use App\Models\BusinessVerification;
use App\Models\User;
use App\Services\Businesses\BusinessReadinessService;
use App\Services\Businesses\BusinessVerificationTransitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ApproveBusinessVerificationAction
{
    public function __construct(
        private readonly BusinessVerificationTransitionService $transitions,
        private readonly BusinessReadinessService $readiness,
        private readonly CreateAuditLogAction $audit,
    ) {}

    public function execute(BusinessVerification $verification, User $actor, ?Request $request = null, ?string $adminNotes = null): BusinessVerification
    {
        return DB::transaction(function () use ($verification, $actor, $request, $adminNotes): BusinessVerification {
            $verification = BusinessVerification::query()->whereKey($verification->id)->lockForUpdate()->firstOrFail();
            $from = $verification->status;

            if ($from === null) {
                throw BusinessVerificationException::notPending();
            }

            $this->transitions->assertCanTransition($from, VerificationRequestStatus::Approved);

            if ($from !== VerificationRequestStatus::Pending) {
                throw BusinessVerificationException::notPending();
            }

            $verification->update([
                'status' => VerificationRequestStatus::Approved,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'admin_notes' => $adminNotes,
            ]);

            $business = $verification->business()->lockForUpdate()->firstOrFail();
            $business->update([
                'verification_status' => BusinessVerificationStatus::Verified,
                'status' => BusinessStatus::Approved,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $actor->id,
                'rejection_reason' => null,
                'verification_note' => null,
            ]);

            $this->audit->execute(
                action: 'business.verification_approved',
                entityType: 'business_verification',
                entityId: $verification->id,
                actor: $actor,
                newValues: [
                    'business_id' => $business->id,
                    'status' => VerificationRequestStatus::Approved->value,
                ],
                request: $request,
            );

            $fresh = $verification->fresh(['business', 'submittedBy', 'reviewedBy']);

            DB::afterCommit(function () use ($fresh, $business): void {
                BusinessVerificationApproved::dispatch($fresh);

                if (app(BusinessReadinessService::class)->isReadyForReservations($business->fresh())) {
                    BusinessBecameReservationReady::dispatch($business->fresh());
                }
            });

            return $fresh;
        });
    }
}
