<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\Enums\BusinessMemberInvitationStatus;
use App\Models\Business;
use App\Models\BusinessMemberInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RevokeBusinessMemberInvitationAction
{
    public function __construct(
        private readonly RecordBusinessStaffAuditAction $staffAudit,
    ) {}

    public function execute(Business $business, BusinessMemberInvitation $invitation, User $actor, ?Request $request = null): BusinessMemberInvitation
    {
        if ($invitation->business_id !== $business->id) {
            abort(404);
        }

        if ($invitation->status !== BusinessMemberInvitationStatus::Pending) {
            throw ValidationException::withMessages([
                'invitation' => [__('business.invitation_not_pending')],
            ]);
        }

        return DB::transaction(function () use ($business, $invitation, $actor, $request): BusinessMemberInvitation {
            $invitation->update([
                'status' => BusinessMemberInvitationStatus::Revoked,
                'responded_at' => now(),
            ]);

            $this->staffAudit->execute(
                action: 'business_staff.invitation_revoked',
                business: $business,
                actor: $actor,
                newValues: ['invitation_id' => $invitation->id],
                request: $request,
            );

            return $invitation->fresh();
        });
    }
}
