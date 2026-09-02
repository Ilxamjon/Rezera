<?php

namespace App\Actions\Businesses;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Businesses\Enums\VerificationRequestStatus;
use App\Events\Businesses\BusinessVerificationRejected;
use App\Exceptions\Businesses\BusinessVerificationException;
use App\Models\BusinessVerification;
use App\Models\User;
use App\Services\Businesses\BusinessVerificationTransitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RejectBusinessVerificationAction
{
    public function __construct(
        private readonly BusinessVerificationTransitionService $transitions,
        private readonly CreateAuditLogAction $audit,
    ) {}

    public function execute(BusinessVerification $verification, User $actor, string $reason, ?Request $request = null, ?string $adminNotes = null): BusinessVerification
    {
        return DB::transaction(function () use ($verification, $actor, $reason, $request, $adminNotes): BusinessVerification {
            $verification = BusinessVerification::query()->whereKey($verification->id)->lockForUpdate()->firstOrFail();
            $from = $verification->status;

            if ($from === null || $from !== VerificationRequestStatus::Pending) {
                throw BusinessVerificationException::notPending();
            }

            $this->transitions->assertCanTransition($from, VerificationRequestStatus::Rejected);

            $verification->update([
                'status' => VerificationRequestStatus::Rejected,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
                'admin_notes' => $adminNotes,
            ]);

            $business = $verification->business()->lockForUpdate()->firstOrFail();
            $business->update([
                'verification_status' => BusinessVerificationStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $actor->id,
            ]);

            $this->audit->execute(
                action: 'business.verification_rejected',
                entityType: 'business_verification',
                entityId: $verification->id,
                actor: $actor,
                newValues: [
                    'business_id' => $business->id,
                    'status' => VerificationRequestStatus::Rejected->value,
                    'reason' => $reason,
                ],
                request: $request,
            );

            $fresh = $verification->fresh(['business', 'submittedBy', 'reviewedBy']);

            DB::afterCommit(fn () => BusinessVerificationRejected::dispatch($fresh));

            return $fresh;
        });
    }
}
